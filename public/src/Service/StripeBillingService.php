<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class StripeBillingService
{
    private ?StripeClient $client = null;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private string $stripeSecretKey,
        private string $stripeWebhookSecret,
        private string $stripePricePlus,
        private string $stripePricePro,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->stripeSecretKey !== ''
            && $this->stripePricePlus !== ''
            && $this->stripePricePro !== '';
    }

    /**
     * @return array{checkoutUrl: string, sessionId: string, provider: string}
     */
    public function createCheckoutSession(
        User $user,
        SubscriptionTier $tier,
        string $successUrl,
        string $cancelUrl,
    ): array {
        if (!$this->isEnabled()) {
            throw new ServiceUnavailableHttpException(null, 'subscription.stripe.disabled');
        }
        if ($tier === SubscriptionTier::Free) {
            throw new BadRequestHttpException('subscription.tier.invalid');
        }
        if (!filter_var($successUrl, FILTER_VALIDATE_URL) || !filter_var($cancelUrl, FILTER_VALIDATE_URL)) {
            throw new BadRequestHttpException('subscription.checkout.urls_invalid');
        }

        $priceId = $tier === SubscriptionTier::Plus ? $this->stripePricePlus : $this->stripePricePro;
        $customerId = $this->ensureCustomer($user);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $user->getId()?->toRfc4122(),
            'metadata' => [
                'userId' => $user->getId()?->toRfc4122() ?? '',
                'tier' => $tier->value,
            ],
            'subscription_data' => [
                'metadata' => [
                    'userId' => $user->getId()?->toRfc4122() ?? '',
                    'tier' => $tier->value,
                ],
            ],
            'allow_promotion_codes' => true,
        ]);

        if (!$session->url) {
            throw new ServiceUnavailableHttpException(null, 'subscription.stripe.session_failed');
        }

        return [
            'checkoutUrl' => $session->url,
            'sessionId' => $session->id,
            'provider' => 'stripe',
            'tier' => $tier->value,
        ];
    }

    public function cancelSubscription(User $user): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $subscriptionId = $user->getStripeSubscriptionId();
        if ($subscriptionId === null || $subscriptionId === '') {
            return;
        }

        try {
            $this->client()->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => true,
            ]);
        } catch (\Throwable) {
            // Local/mock users may have stale ids — still clear local tier in caller.
        }
    }

    public function handleWebhook(string $payload, ?string $signatureHeader): void
    {
        if (!$this->isEnabled()) {
            throw new ServiceUnavailableHttpException(null, 'subscription.stripe.disabled');
        }
        if ($this->stripeWebhookSecret === '') {
            throw new ServiceUnavailableHttpException(null, 'subscription.stripe.webhook_unconfigured');
        }
        if ($signatureHeader === null || $signatureHeader === '') {
            throw new BadRequestHttpException('subscription.stripe.signature_missing');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            throw new BadRequestHttpException('subscription.stripe.signature_invalid');
        }

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($event->data->object),
            'customer.subscription.updated', 'customer.subscription.created' => $this->onSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($event->data->object),
            'invoice.paid' => $this->onInvoicePaid($event->data->object),
            default => null,
        };
    }

    private function ensureCustomer(User $user): string
    {
        if ($user->getStripeCustomerId()) {
            return $user->getStripeCustomerId();
        }

        $customer = $this->client()->customers->create([
            'email' => $user->getEmail(),
            'name' => $user->getFirstname(),
            'metadata' => [
                'userId' => $user->getId()?->toRfc4122() ?? '',
            ],
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->em->flush();

        return $customer->id;
    }

    private function onCheckoutCompleted(object $session): void
    {
        $user = $this->resolveUserFromStripeObject($session);
        if (!$user) {
            return;
        }

        $tierValue = $session->metadata->tier ?? null;
        $tier = SubscriptionTier::tryFrom((string) $tierValue) ?? SubscriptionTier::Plus;
        $subscriptionId = is_string($session->subscription ?? null) ? $session->subscription : ($session->subscription->id ?? null);

        $user->setSubscriptionTier($tier->value);
        if (is_string($subscriptionId) && $subscriptionId !== '') {
            $user->setStripeSubscriptionId($subscriptionId);
            $this->syncExpiryFromSubscription($user, $subscriptionId);
        } else {
            $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+30 days'));
        }
        if (is_string($session->customer ?? null)) {
            $user->setStripeCustomerId($session->customer);
        }
        $this->em->flush();
    }

    private function onSubscriptionUpdated(object $subscription): void
    {
        $user = $this->resolveUserFromSubscription($subscription);
        if (!$user) {
            return;
        }

        $status = (string) ($subscription->status ?? '');
        $tierValue = $subscription->metadata->tier ?? null;
        $tier = SubscriptionTier::tryFrom((string) $tierValue);

        if (\in_array($status, ['active', 'trialing', 'past_due'], true)) {
            if ($tier) {
                $user->setSubscriptionTier($tier->value);
            }
            $user->setStripeSubscriptionId((string) $subscription->id);
            $this->applyPeriodEnd($user, $subscription);
        } elseif (\in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $user->setSubscriptionTier(SubscriptionTier::Free->value);
            $user->setSubscriptionExpiresAt(null);
            $user->setStripeSubscriptionId(null);
        }

        $this->em->flush();
    }

    private function onSubscriptionDeleted(object $subscription): void
    {
        $user = $this->resolveUserFromSubscription($subscription);
        if (!$user) {
            return;
        }

        $user->setSubscriptionTier(SubscriptionTier::Free->value);
        $user->setSubscriptionExpiresAt(null);
        $user->setStripeSubscriptionId(null);
        $this->em->flush();
    }

    private function onInvoicePaid(object $invoice): void
    {
        $subscriptionId = is_string($invoice->subscription ?? null)
            ? $invoice->subscription
            : ($invoice->subscription->id ?? null);
        if (!is_string($subscriptionId) || $subscriptionId === '') {
            return;
        }

        $user = $this->userRepository->findOneBy(['stripeSubscriptionId' => $subscriptionId]);
        if (!$user) {
            return;
        }

        $this->syncExpiryFromSubscription($user, $subscriptionId);
        $this->em->flush();
    }

    private function syncExpiryFromSubscription(User $user, string $subscriptionId): void
    {
        try {
            $subscription = $this->client()->subscriptions->retrieve($subscriptionId);
            $this->applyPeriodEnd($user, $subscription);
        } catch (\Throwable) {
            $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+30 days'));
        }
    }

    private function applyPeriodEnd(User $user, object $subscription): void
    {
        $periodEnd = $subscription->current_period_end ?? null;
        if (is_int($periodEnd) || is_numeric($periodEnd)) {
            $user->setSubscriptionExpiresAt(
                (new \DateTimeImmutable('@' . (int) $periodEnd))->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            );
        }
    }

    private function resolveUserFromStripeObject(object $session): ?User
    {
        $userId = $session->metadata->userId ?? $session->client_reference_id ?? null;
        if (is_string($userId) && $userId !== '') {
            $user = $this->userRepository->find($userId);
            if ($user) {
                return $user;
            }
        }

        $customerId = is_string($session->customer ?? null) ? $session->customer : null;
        if ($customerId) {
            return $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        }

        return null;
    }

    private function resolveUserFromSubscription(object $subscription): ?User
    {
        $userId = $subscription->metadata->userId ?? null;
        if (is_string($userId) && $userId !== '') {
            $user = $this->userRepository->find($userId);
            if ($user) {
                return $user;
            }
        }

        $bySub = $this->userRepository->findOneBy(['stripeSubscriptionId' => (string) $subscription->id]);
        if ($bySub) {
            return $bySub;
        }

        $customerId = is_string($subscription->customer ?? null) ? $subscription->customer : null;
        if ($customerId) {
            return $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        }

        return null;
    }

    private function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient($this->stripeSecretKey);
        }

        return $this->client;
    }
}

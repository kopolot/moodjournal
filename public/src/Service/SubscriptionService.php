<?php

namespace App\Service;

use App\Dto\SubscriptionCheckoutDto;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubscriptionService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private StripeBillingService $stripeBillingService,
    ) {
    }

    public function billingProvider(): string
    {
        return $this->stripeBillingService->isEnabled() ? 'stripe' : 'mock';
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout(User $user, SubscriptionCheckoutDto $dto): array
    {
        $tier = SubscriptionTier::tryFrom($dto->tier);
        if (!$tier || $tier === SubscriptionTier::Free) {
            throw new BadRequestHttpException('subscription.tier.invalid');
        }

        if ($this->stripeBillingService->isEnabled()) {
            if ($dto->successUrl === null || $dto->cancelUrl === null) {
                throw new BadRequestHttpException('subscription.checkout.urls_invalid');
            }

            return $this->stripeBillingService->createCheckoutSession(
                $user,
                $tier,
                $dto->successUrl,
                $dto->cancelUrl,
            );
        }

        return $this->mockCheckout($user, $tier, $dto);
    }

    /**
     * Fake payment processor — used when Stripe keys are not configured.
     *
     * @return array<string, mixed>
     */
    private function mockCheckout(User $user, SubscriptionTier $tier, SubscriptionCheckoutDto $dto): array
    {
        $digits = preg_replace('/\D+/', '', (string) $dto->cardNumber) ?? '';
        if (strlen($digits) < 13 || strlen($digits) > 19) {
            throw new UnprocessableEntityHttpException('subscription.card.invalid');
        }
        if (str_ends_with($digits, '0000')) {
            throw new UnprocessableEntityHttpException('subscription.payment.declined');
        }
        if ($dto->cardholderName === null || trim($dto->cardholderName) === '') {
            throw new UnprocessableEntityHttpException('subscription.card.name_required');
        }

        return $this->em->wrapInTransaction(function () use ($user, $tier, $digits, $dto): array {
            /** @var User|null $locked */
            $locked = $this->em->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$locked) {
                throw new NotFoundHttpException('user.not_found');
            }

            $expiresAt = (new \DateTimeImmutable('now'))->modify('+30 days');
            $locked->setSubscriptionTier($tier->value);
            $locked->setSubscriptionExpiresAt($expiresAt);
            $this->em->flush();

            return [
                'provider' => 'mock',
                'paymentId' => 'pay_demo_' . bin2hex(random_bytes(8)),
                'status' => 'succeeded',
                'tier' => $tier->value,
                'expiresAt' => $expiresAt->format(DATE_ATOM),
                'aiAnalysisUnlocked' => $tier->unlocksAi(),
                'receipt' => [
                    'amount' => $this->priceFor($tier),
                    'currency' => 'PLN',
                    'maskedCard' => '**** **** **** ' . substr($digits, -4),
                    'cardholderName' => $dto->cardholderName,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function current(User $user): array
    {
<<<<<<< HEAD
        $tier = SubscriptionTier::effectiveFor($user);
=======
        $tier = SubscriptionTier::tryFrom($user->getSubscriptionTier()) ?? SubscriptionTier::Free;
        $expiresAt = $user->getSubscriptionExpiresAt();
        if ($tier !== SubscriptionTier::Free && $expiresAt !== null && $expiresAt < new \DateTimeImmutable('now')) {
            $tier = SubscriptionTier::Free;
        }
>>>>>>> 4fd07e2 (feat(billing): add Stripe Checkout subscriptions with webhook renewals)

        return [
            'tier' => $tier->value,
            'expiresAt' => $expiresAt?->format(DATE_ATOM),
            'aiAnalysisUnlocked' => $tier->unlocksAi(),
<<<<<<< HEAD
            'advancedReportsUnlocked' => $tier->unlocksAdvancedReports(),
=======
            'billingProvider' => $this->billingProvider(),
>>>>>>> 4fd07e2 (feat(billing): add Stripe Checkout subscriptions with webhook renewals)
            'plans' => SubscriptionTier::catalog(),
        ];
    }

    public function cancel(User $user): array
    {
        return $this->em->wrapInTransaction(function () use ($user): array {
            /** @var User|null $locked */
            $locked = $this->em->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$locked) {
                throw new NotFoundHttpException('user.not_found');
            }

            if ($this->billingProvider() === 'stripe' && $locked->getStripeSubscriptionId()) {
                // Renewals stop at period end; webhook clears the tier afterwards.
                $this->stripeBillingService->cancelSubscription($locked);
            } else {
                $locked->setSubscriptionTier(SubscriptionTier::Free->value);
                $locked->setSubscriptionExpiresAt(null);
                $locked->setStripeSubscriptionId(null);
            }
            $this->em->flush();

            return $this->current($locked);
        });
    }

    private function priceFor(SubscriptionTier $tier): float
    {
        foreach (SubscriptionTier::catalog() as $plan) {
            if ($plan['id'] === $tier->value) {
                return (float) $plan['priceMonthly'];
            }
        }

        return 0.0;
    }
}

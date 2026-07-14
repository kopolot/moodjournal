<?php

namespace App\Service;

use App\Dto\SubscriptionCheckoutDto;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubscriptionService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Fake payment processor — validates card shape and "charges" successfully.
     *
     * @return array<string, mixed>
     */
    public function checkout(User $user, SubscriptionCheckoutDto $dto): array
    {
        $tier = SubscriptionTier::tryFrom($dto->tier);
        if (!$tier || $tier === SubscriptionTier::Free) {
            throw new BadRequestHttpException('subscription.tier.invalid');
        }

        $digits = preg_replace('/\D+/', '', $dto->cardNumber) ?? '';
        if (strlen($digits) < 13 || strlen($digits) > 19) {
            throw new UnprocessableEntityHttpException('subscription.card.invalid');
        }
        // Demo decline: cards ending with 0000
        if (str_ends_with($digits, '0000')) {
            throw new UnprocessableEntityHttpException('subscription.payment.declined');
        }

        $expiresAt = (new \DateTimeImmutable('now'))->modify('+30 days');
        $user->setSubscriptionTier($tier->value);
        $user->setSubscriptionExpiresAt($expiresAt);
        $this->userRepository->save($user);

        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    public function current(User $user): array
    {
        $tier = SubscriptionTier::tryFrom($user->getSubscriptionTier()) ?? SubscriptionTier::Free;

        return [
            'tier' => $tier->value,
            'expiresAt' => $user->getSubscriptionExpiresAt()?->format(DATE_ATOM),
            'aiAnalysisUnlocked' => $tier->unlocksAi(),
            'plans' => SubscriptionTier::catalog(),
        ];
    }

    public function cancel(User $user): array
    {
        $user->setSubscriptionTier(SubscriptionTier::Free->value);
        $user->setSubscriptionExpiresAt(null);
        $this->userRepository->save($user);

        return $this->current($user);
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

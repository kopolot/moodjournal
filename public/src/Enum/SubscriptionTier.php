<?php

namespace App\Enum;

enum SubscriptionTier: string
{
    case Free = 'free';
    case Plus = 'plus';
    case Pro = 'pro';

    public function unlocksAi(): bool
    {
        return $this === self::Plus || $this === self::Pro;
    }

    public function unlocksAdvancedReports(): bool
    {
        return $this === self::Pro;
    }

    /**
     * Active paid tier considering expiry; expired paid plans fall back to Free.
     */
    public static function effectiveFor(\App\Entity\User $user): self
    {
        $tier = self::tryFrom($user->getSubscriptionTier()) ?? self::Free;
        if ($tier === self::Free) {
            return self::Free;
        }

        $expiresAt = $user->getSubscriptionExpiresAt();
        if ($expiresAt !== null && $expiresAt < new \DateTimeImmutable('now')) {
            return self::Free;
        }

        return $tier;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return [
            [
                'id' => self::Free->value,
                'name' => 'Free',
                'priceMonthly' => 0,
                'currency' => 'PLN',
                'features' => ['checkins', 'history', 'streaks'],
            ],
            [
                'id' => self::Plus->value,
                'name' => 'Plus',
                'priceMonthly' => 19.99,
                'currency' => 'PLN',
                'features' => ['checkins', 'history', 'streaks', 'ai_insights'],
            ],
            [
                'id' => self::Pro->value,
                'name' => 'Pro',
                'priceMonthly' => 39.99,
                'currency' => 'PLN',
                'features' => ['checkins', 'history', 'streaks', 'ai_insights', 'advanced_reports'],
            ],
        ];
    }
}

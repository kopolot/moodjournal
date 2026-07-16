<?php

namespace App\Tests\Unit\Enum;

use App\Entity\User;
use App\Enum\SubscriptionTier;
use PHPUnit\Framework\TestCase;

final class SubscriptionTierTest extends TestCase
{
    public function testExpiredPaidTierFallsBackToFree(): void
    {
        $user = new User();
        $user->setSubscriptionTier('plus');
        $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('-1 day'));

        $this->assertSame(SubscriptionTier::Free, SubscriptionTier::effectiveFor($user));
        $this->assertFalse(SubscriptionTier::effectiveFor($user)->unlocksAi());
    }

    public function testActivePlusUnlocksAiButNotReports(): void
    {
        $user = new User();
        $user->setSubscriptionTier('plus');
        $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+10 days'));

        $tier = SubscriptionTier::effectiveFor($user);
        $this->assertSame(SubscriptionTier::Plus, $tier);
        $this->assertTrue($tier->unlocksAi());
        $this->assertFalse($tier->unlocksAdvancedReports());
    }

    public function testProUnlocksReports(): void
    {
        $user = new User();
        $user->setSubscriptionTier('pro');
        $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+10 days'));

        $tier = SubscriptionTier::effectiveFor($user);
        $this->assertTrue($tier->unlocksAi());
        $this->assertTrue($tier->unlocksAdvancedReports());
    }
}

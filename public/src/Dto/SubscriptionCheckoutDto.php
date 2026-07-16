<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SubscriptionCheckoutDto
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['plus', 'pro'])]
    public string $tier;

    /** Mock checkout fields (required only when Stripe is disabled). */
    public ?string $cardNumber = null;

    public ?string $expiry = null;

    public ?string $cvc = null;

    public ?string $cardholderName = null;

    /** Stripe Checkout return URLs (required when Stripe is enabled). */
    public ?string $successUrl = null;

    public ?string $cancelUrl = null;
}

<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SubscriptionCheckoutDto
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['plus', 'pro'])]
    public string $tier;

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 24)]
    public string $cardNumber;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{2}\/\d{2}$/')]
    public string $expiry;

    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 4)]
    public string $cvc;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 80)]
    public string $cardholderName;
}

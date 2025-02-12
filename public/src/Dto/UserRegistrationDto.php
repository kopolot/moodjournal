<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Translation\TranslatableMessage;

class UserRegistrationDto{

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 255)]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\EqualTo(propertyPath: "password", message: "user.registration.password_mismatch")]
    public string $repeatPassword;

    #[Assert\IsTrue(message: new TranslatableMessage( 'user.registration.accept_policy'))]
    public bool $acceptPrivacyPolicy;
}
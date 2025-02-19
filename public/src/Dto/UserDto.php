<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserDto{
    #[Assert\NotBlank( groups: [ 'create'])]
    #[Assert\Length(min: 2, max: 50, groups: [ 'create'])]
    public string $firstName;

    #[Assert\NotBlank( groups: [ 'create', 'login'])]
    #[Assert\Email( groups: [ 'create'])]
    public string $email;

    #[Assert\NotBlank( groups: [ 'create'])]
    #[Assert\Length(min: 6, max: 255, groups: [ 'create', 'login'])]
    public string $password;

    #[Assert\NotBlank( groups: [ 'create'])]
    #[Assert\EqualTo(propertyPath: "password", groups: [ 'create'])]
    public string $repeatPassword;

    #[Assert\IsTrue( groups: [ 'create'])]
    public bool $acceptPrivacyPolicy;
}
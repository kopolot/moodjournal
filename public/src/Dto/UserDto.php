<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use App\Translation\UserTranslationKeys;

class UserDto{
    #[Assert\NotBlank( groups: [ 'create'], message: UserTranslationKeys::USER_FIRST_NAME_NOT_BLANK)]
    #[Assert\Length(min: 2, max: 50, groups: [ 'create'], minMessage: UserTranslationKeys::USER_FIRST_NAME_LENGTH, maxMessage: UserTranslationKeys::USER_FIRST_NAME_LENGTH )]
    public string $firstname;

    #[Assert\NotBlank( groups: [ 'create', 'login', 'forgotpassword'], message: UserTranslationKeys::USER_EMAIL_NOT_BLANK)]
    #[Assert\Email( groups: [ 'create'], message: UserTranslationKeys::USER_EMAIL_INVALID)]
    public string $email;

    #[Assert\NotBlank( groups: [ 'create'], message: UserTranslationKeys::USER_PASSWORD_NOT_BLANK)]
    #[Assert\Length(min: 6, max: 255, groups: [ 'create', 'login'], minMessage: UserTranslationKeys::USER_PASSWORD_LENGTH, maxMessage: UserTranslationKeys::USER_PASSWORD_LENGTH )]
    public string $password;

    #[Assert\NotBlank( groups: [ 'create'], message: UserTranslationKeys::USER_REPEAT_PASSWORD_NOT_BLANK)]
    #[Assert\EqualTo(propertyPath: "password", groups: [ 'create'], message: UserTranslationKeys::USER_PASSWORD_NOT_MATCH)]
    public string $repeatPassword;

    #[Assert\IsTrue( groups: [ 'create'], message: UserTranslationKeys::USER_PRIVACY_POLICY_NOT_ACCEPTED)]
    public bool $acceptPrivacyPolicy;
}
<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use App\Translation\UserTranslationKeys;
use App\ValueObject\UserPreferences;

class UserDto
{
    #[Assert\NotBlank(groups: ['create'], message: UserTranslationKeys::USER_FIRST_NAME_NOT_BLANK)]
    #[Assert\Length(min: 2, max: 50, groups: ['create', 'edit'], minMessage: UserTranslationKeys::USER_FIRST_NAME_LENGTH, maxMessage: UserTranslationKeys::USER_FIRST_NAME_LENGTH)]
    public ?string $firstname = null;

    #[Assert\NotBlank(groups: ['create', 'login', 'forgotpassword'], message: UserTranslationKeys::USER_EMAIL_NOT_BLANK)]
    #[Assert\Email(groups: ['create', 'login', 'forgotpassword'], message: UserTranslationKeys::USER_EMAIL_INVALID)]
    public ?string $email = null;

    #[Assert\NotBlank(groups: ['create'], message: UserTranslationKeys::USER_PASSWORD_NOT_BLANK)]
    #[Assert\Length(min: 6, max: 255, groups: ['create', 'login'], minMessage: UserTranslationKeys::USER_PASSWORD_LENGTH, maxMessage: UserTranslationKeys::USER_PASSWORD_LENGTH)]
    public ?string $password = null;

    #[Assert\NotBlank(groups: ['create'], message: UserTranslationKeys::USER_REPEAT_PASSWORD_NOT_BLANK)]
    #[Assert\EqualTo(propertyPath: "password", groups: ['create'], message: UserTranslationKeys::USER_PASSWORD_NOT_MATCH)]
    public ?string $repeatPassword = null;

    #[Assert\IsTrue(groups: ['create'], message: UserTranslationKeys::USER_PRIVACY_POLICY_NOT_ACCEPTED)]
    public ?bool $acceptPrivacyPolicy = null;

    #[Assert\Type(type: UserPreferences::class, groups: ['create', 'edit'], message: UserTranslationKeys::USER_PREFERENCES_INVALID)]
    public ?UserPreferences $preferences = null;
}

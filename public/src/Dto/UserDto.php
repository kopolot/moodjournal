<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use App\Translation\UserTranslationKeys;

class UserDto
{
    #[Assert\NotBlank(groups: ['create'], message: UserTranslationKeys::USER_FIRST_NAME_NOT_BLANK)]
    #[Assert\Length(min: 2, max: 50, groups: ['create', 'edit'], minMessage: UserTranslationKeys::USER_FIRST_NAME_LENGTH, maxMessage: UserTranslationKeys::USER_FIRST_NAME_LENGTH)]
    public ?string $firstname = null;

    #[Assert\NotBlank(groups: ['create', 'login', 'forgotpassword'], message: UserTranslationKeys::USER_EMAIL_NOT_BLANK)]
    #[Assert\Email(groups: ['create', 'login', 'forgotpassword'], message: UserTranslationKeys::USER_EMAIL_INVALID)]
    public ?string $email = null;

    #[Assert\NotBlank(groups: ['create', 'reset_password', 'change_password'], message: UserTranslationKeys::USER_PASSWORD_NOT_BLANK)]
    #[Assert\Length(min: 6, max: 255, groups: ['create', 'login', 'reset_password', 'change_password'], minMessage: UserTranslationKeys::USER_PASSWORD_LENGTH, maxMessage: UserTranslationKeys::USER_PASSWORD_LENGTH)]
    public ?string $password = null;

    #[Assert\NotBlank(groups: ['create', 'reset_password', 'change_password'], message: UserTranslationKeys::USER_REPEAT_PASSWORD_NOT_BLANK)]
    #[Assert\EqualTo(propertyPath: 'password', groups: ['create', 'reset_password', 'change_password'], message: UserTranslationKeys::USER_PASSWORD_NOT_MATCH)]
    public ?string $repeatPassword = null;

    #[Assert\NotBlank(groups: ['reset_password'], message: UserTranslationKeys::USER_RESET_TOKEN_INVALID)]
    public ?string $token = null;

    #[Assert\NotBlank(groups: ['change_password'], message: UserTranslationKeys::USER_CURRENT_PASSWORD_INVALID)]
    public ?string $currentPassword = null;

    #[Assert\IsTrue(groups: ['create'], message: UserTranslationKeys::USER_PRIVACY_POLICY_NOT_ACCEPTED)]
    public ?bool $acceptPrivacyPolicy = null;

    #[Assert\Valid(groups: ['edit'])]
    public ?UserPreferencesPatchDto $preferences = null;
}

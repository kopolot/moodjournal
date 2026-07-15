<?php

namespace App\Dto;

use App\Enum\Language;
use App\Translation\UserTranslationKeys;
use Symfony\Component\Validator\Constraints as Assert;

/** Partial preferences update — null fields are left unchanged. */
class UserPreferencesPatchDto
{
    #[Assert\Choice(callback: [Language::class, 'values'], groups: ['edit'], message: UserTranslationKeys::USER_EDIT_LANGUAGE_ERROR)]
    public ?string $language = null;

    public ?bool $darkMode = null;

    public ?bool $dailyNotifications = null;
}

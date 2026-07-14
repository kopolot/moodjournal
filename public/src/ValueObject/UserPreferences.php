<?php

namespace App\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\Language;
use App\Translation\UserTranslationKeys;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserPreferences
{
    const DEFAULT_VALUES = [
        'language' => Language::EN,
        'darkMode' => false,
        'dailyNotifications' => true,
    ];

    #[Assert\Type(type: Language::class, message: 'Invalid language type')]
    private ?Language $language;
    private ?bool $darkMode;
    private ?bool $dailyNotifications;

    public function __construct(
        ?Language $language = null,
        ?bool $darkMode = null,
        ?bool $dailyNotifications = null,
    ) {
        $this->language = $language ?? static::DEFAULT_VALUES['language'];
        $this->darkMode = $darkMode;
        $this->dailyNotifications = $dailyNotifications;
    }

    public function getLanguage(): Language
    {
        return $this->language ?? static::DEFAULT_VALUES['language'];
    }

    public function setLanguage(string|Language $language): void
    {
        if ($language instanceof Language) {
            $this->language = $language;
            return;
        }
        if (!Language::tryFrom($language)) {
            throw new UnprocessableEntityHttpException(UserTranslationKeys::USER_EDIT_LANGUAGE_ERROR);
        }
        $this->language = Language::from($language);
    }

    public function isDarkMode(): bool
    {
        return $this->darkMode ?? static::DEFAULT_VALUES['darkMode'];
    }

    public function setDarkMode(bool $darkMode): void
    {
        $this->darkMode = $darkMode;
    }

    public function isDailyNotifications(): bool
    {
        return $this->dailyNotifications ?? static::DEFAULT_VALUES['dailyNotifications'];
    }

    public function setDailyNotifications(bool $dailyNotifications): void
    {
        $this->dailyNotifications = $dailyNotifications;
    }

    public function __serialize(): array
    {
        return [
            'language' => $this->language instanceof Language
                ? $this->language->value
                : static::DEFAULT_VALUES['language']->value,
            'darkMode' => $this->darkMode ?? static::DEFAULT_VALUES['darkMode'],
            'dailyNotifications' => $this->dailyNotifications ?? static::DEFAULT_VALUES['dailyNotifications'],
        ];
    }

    public function __unserialize(array $data)
    {
        $lang = $data['language'] ?? static::DEFAULT_VALUES['language']->value;
        $this->language = is_string($lang) && Language::tryFrom($lang)
            ? Language::from($lang)
            : static::DEFAULT_VALUES['language'];
        $this->darkMode = $data['darkMode'] ?? static::DEFAULT_VALUES['darkMode'];
        $this->dailyNotifications = $data['dailyNotifications'] ?? static::DEFAULT_VALUES['dailyNotifications'];
    }
}

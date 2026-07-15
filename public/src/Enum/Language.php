<?php

namespace App\Enum;

enum Language: string
{
    case EN = 'en_EN';
    case FR = 'fr_FR';
    case ES = 'es_ES';
    case PL = 'pl_PL';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

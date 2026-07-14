<?php

namespace App\Dto;

use App\Translation\MoodTranslationKeys;
use Symfony\Component\Validator\Constraints as Assert;

class MoodEntryDto
{
    #[Assert\NotBlank(groups: ['create'], message: MoodTranslationKeys::MOOD_OVERALL_REQUIRED)]
    #[Assert\Range(
        min: 1,
        max: 6,
        groups: ['create', 'edit'],
        notInRangeMessage: MoodTranslationKeys::MOOD_SCORE_RANGE
    )]
    public ?int $overallMood = null;

    /**
     * @var array<string, array{score?: int|null, note?: string|null}>|null
     */
    #[Assert\NotNull(groups: ['create'], message: MoodTranslationKeys::MOOD_ASPECTS_REQUIRED)]
    #[Assert\Type(type: 'array', groups: ['create', 'edit'])]
    public ?array $aspects = null;

    #[Assert\Length(max: 1000, groups: ['create', 'edit'], maxMessage: MoodTranslationKeys::MOOD_NOTE_LENGTH)]
    public ?string $note = null;
}

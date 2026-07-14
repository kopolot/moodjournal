<?php

namespace App\Service;

use App\Dto\MoodEntryDto;
use App\Entity\MoodEntry;
use App\Entity\User;
use App\Repository\MoodEntryRepository;
use App\Repository\UserRepository;
use App\Translation\MoodTranslationKeys;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MoodService
{
    private const BASE_XP = 15;
    private const ASPECT_SCORE_XP = 3;
    private const ASPECT_NOTE_XP = 5;
    private const OVERALL_NOTE_XP = 5;
    private const FIRST_OF_DAY_BONUS = 10;
    private const STREAK_BONUS_PER_DAY = 2;
    private const XP_PER_LEVEL = 100;

    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function create(User $user, MoodEntryDto $dto): MoodEntry
    {
        $aspects = $this->normalizeAspects($dto->aspects ?? []);
        $today = new \DateTimeImmutable('today');
        $isFirstToday = !$this->moodEntryRepository->hasEntryOnDate($user, $today);

        $entry = new MoodEntry();
        $entry->setUser($user);
        $entry->setOverallMood((int) $dto->overallMood);
        $entry->setAspects($aspects);
        $entry->setNote($this->normalizeNote($dto->note));

        $xp = $this->calculateXp($entry, $isFirstToday, $user->getCurrentStreak());
        $entry->setXpEarned($xp);

        if ($isFirstToday) {
            $this->applyStreak($user, $today);
        }

        $user->addXp($xp);
        $this->moodEntryRepository->save($entry);
        $this->userRepository->save($user);

        return $entry;
    }

    public function update(User $user, string $id, MoodEntryDto $dto): MoodEntry
    {
        $entry = $this->requireOwnedEntry($user, $id);

        if ($dto->overallMood !== null) {
            $entry->setOverallMood($dto->overallMood);
        }
        if ($dto->aspects !== null) {
            $entry->setAspects($this->normalizeAspects($dto->aspects));
        }
        if ($dto->note !== null) {
            $entry->setNote($this->normalizeNote($dto->note));
        }

        $this->moodEntryRepository->save($entry);

        return $entry;
    }

    public function delete(User $user, string $id): void
    {
        $entry = $this->requireOwnedEntry($user, $id);
        $this->moodEntryRepository->delete($entry);
    }

    public function get(User $user, string $id): MoodEntry
    {
        return $this->requireOwnedEntry($user, $id);
    }

    /**
     * @return array{items: list<MoodEntry>, total: int}
     */
    public function list(User $user, int $limit = 30, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return [
            'items' => $this->moodEntryRepository->findByUser($user, $limit, $offset),
            'total' => $this->moodEntryRepository->countByUser($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $xpTotal = $user->getXpTotal();
        $level = intdiv($xpTotal, self::XP_PER_LEVEL) + 1;
        $xpIntoLevel = $xpTotal % self::XP_PER_LEVEL;

        return [
            'xpTotal' => $xpTotal,
            'level' => $level,
            'xpIntoLevel' => $xpIntoLevel,
            'xpPerLevel' => self::XP_PER_LEVEL,
            'currentStreak' => $user->getCurrentStreak(),
            'longestStreak' => $user->getLongestStreak(),
            'lastMoodDate' => $user->getLastMoodDate()?->format('Y-m-d'),
            'loggedToday' => $this->moodEntryRepository->hasEntryOnDate($user, $today),
            'entryCount' => $this->moodEntryRepository->countByUser($user),
            'averageOverall7d' => $this->moodEntryRepository->averageOverallForUser($user, 7),
            'subscriptionTier' => $user->getSubscriptionTier(),
            'aiAnalysisUnlocked' => $user->getSubscriptionTier() === 'plus',
        ];
    }

    private function requireOwnedEntry(User $user, string $id): MoodEntry
    {
        $entry = $this->moodEntryRepository->findOneForUser($user, $id);
        if (!$entry) {
            throw new NotFoundHttpException(MoodTranslationKeys::MOOD_NOT_FOUND);
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, array{score: int, note: ?string}>
     */
    private function normalizeAspects(array $raw): array
    {
        $aspects = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            if (!array_key_exists($key, $raw) || !is_array($raw[$key])) {
                throw new UnprocessableEntityHttpException(MoodTranslationKeys::MOOD_ASPECTS_INVALID);
            }

            $score = $raw[$key]['score'] ?? null;
            if (!is_int($score) && !(is_string($score) && ctype_digit($score))) {
                throw new UnprocessableEntityHttpException(MoodTranslationKeys::MOOD_SCORE_RANGE);
            }
            $score = (int) $score;
            if ($score < 1 || $score > 6) {
                throw new UnprocessableEntityHttpException(MoodTranslationKeys::MOOD_SCORE_RANGE);
            }

            $note = $raw[$key]['note'] ?? null;
            if ($note !== null) {
                $note = trim((string) $note);
                if ($note === '') {
                    $note = null;
                } elseif (mb_strlen($note) > 500) {
                    throw new UnprocessableEntityHttpException(MoodTranslationKeys::MOOD_NOTE_LENGTH);
                }
            }

            $aspects[$key] = [
                'score' => $score,
                'note' => $note,
            ];
        }

        return $aspects;
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }
        $note = trim($note);

        return $note === '' ? null : $note;
    }

    private function calculateXp(MoodEntry $entry, bool $isFirstToday, int $currentStreak): int
    {
        $xp = self::BASE_XP;
        foreach ($entry->getAspects() as $aspect) {
            $xp += self::ASPECT_SCORE_XP;
            if (!empty($aspect['note'])) {
                $xp += self::ASPECT_NOTE_XP;
            }
        }
        if ($entry->getNote()) {
            $xp += self::OVERALL_NOTE_XP;
        }
        if ($isFirstToday) {
            $xp += self::FIRST_OF_DAY_BONUS;
            $xp += min(20, $currentStreak * self::STREAK_BONUS_PER_DAY);
        }

        return $xp;
    }

    private function applyStreak(User $user, \DateTimeImmutable $today): void
    {
        $last = $user->getLastMoodDate();
        if ($last === null) {
            $user->setCurrentStreak(1);
        } else {
            $lastDay = $last->setTime(0, 0, 0);
            $yesterday = $today->modify('-1 day');
            if ($lastDay->format('Y-m-d') === $today->format('Y-m-d')) {
                // already logged today — streak unchanged
            } elseif ($lastDay->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                $user->setCurrentStreak($user->getCurrentStreak() + 1);
            } else {
                $user->setCurrentStreak(1);
            }
        }

        if ($user->getCurrentStreak() > $user->getLongestStreak()) {
            $user->setLongestStreak($user->getCurrentStreak());
        }
        $user->setLastMoodDate($today);
    }
}

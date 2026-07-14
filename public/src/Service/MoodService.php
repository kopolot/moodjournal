<?php

namespace App\Service;

use App\Dto\MoodEntryDto;
use App\Entity\MoodEntry;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Repository\MoodEntryRepository;
use App\Repository\UserRepository;
use App\Translation\MoodTranslationKeys;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MoodService
{
    private const BASE_XP = 15;
    private const ASPECT_SCORE_XP = 3;
    private const ASPECT_NOTE_XP = 5;
    private const OVERALL_NOTE_XP = 5;
    private const FIRST_OF_DAY_BONUS = 10;
    private const STREAK_BONUS_PER_DAY = 2;
    private const XP_PER_LEVEL = 100;
    private const HINTS_CACHE_TTL = 900;

    /** ~1.5 weeks */
    public const HINT_WINDOW_DAYS = 11;
    public const NOTE_DEVIATION_THRESHOLD = 1.0;
    public const NOTE_DROP_THRESHOLD = 0.75;

    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
        private UserRepository $userRepository,
        private CacheInterface $moodCache,
    ) {
    }

    public function create(User $user, MoodEntryDto $dto): MoodEntry
    {
        $hints = $this->checkinHints($user);
        $aspects = $this->normalizeAspects($dto->aspects ?? [], $hints);
        $today = new \DateTimeImmutable('today');
        $isFirstToday = !$this->moodEntryRepository->hasEntryOnDate($user, $today);

        $overallMood = (int) $dto->overallMood;
        $overallNote = $this->normalizeNote($dto->note);
        if ($this->isOverallNoteRequired($overallMood, $hints)) {
            $this->assertNoteLength($overallNote);
        }

        $entry = new MoodEntry();
        $entry->setUser($user);
        $entry->setOverallMood($overallMood);
        $entry->setAspects($aspects);
        $entry->setNote($overallNote);

        $xp = $this->calculateXp($entry, $isFirstToday, $user->getCurrentStreak());
        $entry->setXpEarned($xp);

        if ($isFirstToday) {
            $this->applyStreak($user, $today);
        }

        $user->addXp($xp);
        $this->moodEntryRepository->save($entry);
        $this->userRepository->save($user);
        $this->invalidateHintsCache($user);

        return $entry;
    }

    public function update(User $user, string $id, MoodEntryDto $dto): MoodEntry
    {
        $entry = $this->requireOwnedEntry($user, $id);
        $hints = $this->checkinHints($user);

        if ($dto->overallMood !== null) {
            $entry->setOverallMood($dto->overallMood);
        }
        if ($dto->aspects !== null) {
            $entry->setAspects($this->normalizeAspects($dto->aspects, $hints));
        }
        if ($dto->note !== null) {
            $entry->setNote($this->normalizeNote($dto->note));
        }

        $this->moodEntryRepository->save($entry);
        $this->invalidateHintsCache($user);

        return $entry;
    }

    public function delete(User $user, string $id): void
    {
        $entry = $this->requireOwnedEntry($user, $id);
        $this->moodEntryRepository->delete($entry);
        $this->invalidateHintsCache($user);
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
        $tier = SubscriptionTier::tryFrom($user->getSubscriptionTier()) ?? SubscriptionTier::Free;

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
            'subscriptionTier' => $tier->value,
            'subscriptionExpiresAt' => $user->getSubscriptionExpiresAt()?->format(DATE_ATOM),
            'aiAnalysisUnlocked' => $tier->unlocksAi(),
        ];
    }

    /**
     * Hints for when an aspect/overall note is required during check-in.
     * Cached in Redis (pool cache.mood); invalidated on mood write.
     *
     * @return array<string, mixed>
     */
    public function checkinHints(User $user): array
    {
        $cacheKey = $this->hintsCacheKey($user);

        /** @var array<string, mixed> $hints */
        $hints = $this->moodCache->get($cacheKey, function (ItemInterface $item) use ($user): array {
            $item->expiresAfter(self::HINTS_CACHE_TTL);

            return $this->computeCheckinHints($user);
        });

        return $hints;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeCheckinHints(User $user): array
    {
        $now = new \DateTimeImmutable('today');
        $recentFrom = $now->modify(sprintf('-%d days', self::HINT_WINDOW_DAYS));
        $priorFrom = $recentFrom->modify(sprintf('-%d days', self::HINT_WINDOW_DAYS));

        $recent = $this->moodEntryRepository->findBetween($user, $recentFrom, $now->modify('+1 day'));
        $prior = $this->moodEntryRepository->findBetween($user, $priorFrom, $recentFrom);

        $overallAvg = $this->averageOverall($recent);
        $priorOverallAvg = $this->averageOverall($prior);
        $noticeableDrop = $overallAvg !== null
            && $priorOverallAvg !== null
            && ($priorOverallAvg - $overallAvg) >= self::NOTE_DROP_THRESHOLD;

        return [
            'windowDays' => self::HINT_WINDOW_DAYS,
            'deviationThreshold' => self::NOTE_DEVIATION_THRESHOLD,
            'dropThreshold' => self::NOTE_DROP_THRESHOLD,
            'aspectAverages' => $this->averageAspects($recent),
            'overallAverage' => $overallAvg,
            'priorOverallAverage' => $priorOverallAvg,
            'noticeableDrop' => $noticeableDrop,
            'noteMinLength' => MoodEntry::ASPECT_NOTE_MIN_LENGTH,
        ];
    }

    private function invalidateHintsCache(User $user): void
    {
        $this->moodCache->delete($this->hintsCacheKey($user));
    }

    private function hintsCacheKey(User $user): string
    {
        return 'mood.hints.' . $user->getId()?->toRfc4122();
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
     * @param array<string, mixed> $hints
     * @return array<string, array{score: int, note: ?string}>
     */
    private function normalizeAspects(array $raw, array $hints): array
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

            $note = $this->normalizeNote(isset($raw[$key]['note']) ? (string) $raw[$key]['note'] : null);
            if ($this->isAspectNoteRequired($key, $score, $hints)) {
                $this->assertNoteLength($note);
            }

            $aspects[$key] = [
                'score' => $score,
                'note' => $note,
            ];
        }

        return $aspects;
    }

    /**
     * @param array<string, mixed> $hints
     */
    private function isAspectNoteRequired(string $key, int $score, array $hints): bool
    {
        if (!empty($hints['noticeableDrop'])) {
            return true;
        }

        $avg = $hints['aspectAverages'][$key] ?? null;
        // Cold start / no history yet — always ask for context.
        if ($avg === null) {
            return true;
        }

        return abs($score - (float) $avg) >= self::NOTE_DEVIATION_THRESHOLD;
    }

    /**
     * @param array<string, mixed> $hints
     */
    private function isOverallNoteRequired(int $overallMood, array $hints): bool
    {
        if (!empty($hints['noticeableDrop'])) {
            return true;
        }

        $avg = $hints['overallAverage'] ?? null;
        // Cold start / no history yet — always ask for context.
        if ($avg === null) {
            return true;
        }

        return abs($overallMood - (float) $avg) >= self::NOTE_DEVIATION_THRESHOLD;
    }

    private function assertNoteLength(?string $note): void
    {
        if ($note === null || mb_strlen($note) < MoodEntry::ASPECT_NOTE_MIN_LENGTH) {
            throw new UnprocessableEntityHttpException(MoodTranslationKeys::MOOD_ASPECT_NOTE_REQUIRED);
        }
        if (mb_strlen($note) > MoodEntry::ASPECT_NOTE_MAX_LENGTH) {
            throw new UnprocessableEntityHttpException(MoodTranslationKeys::MOOD_NOTE_LENGTH);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }
        $note = trim($note);

        return $note === '' ? null : $note;
    }

    /**
     * @param list<MoodEntry> $entries
     */
    private function averageOverall(array $entries): ?float
    {
        if ($entries === []) {
            return null;
        }
        $sum = 0;
        foreach ($entries as $entry) {
            $sum += $entry->getOverallMood();
        }

        return round($sum / count($entries), 2);
    }

    /**
     * @param list<MoodEntry> $entries
     * @return array<string, float>
     */
    private function averageAspects(array $entries): array
    {
        $sums = [];
        $counts = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            $sums[$key] = 0.0;
            $counts[$key] = 0;
        }

        foreach ($entries as $entry) {
            foreach ($entry->getAspects() as $key => $aspect) {
                if (!isset($sums[$key]) || !isset($aspect['score'])) {
                    continue;
                }
                $sums[$key] += (int) $aspect['score'];
                ++$counts[$key];
            }
        }

        $averages = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            if ($counts[$key] > 0) {
                $averages[$key] = round($sums[$key] / $counts[$key], 2);
            }
        }

        return $averages;
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

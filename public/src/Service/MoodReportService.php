<?php

namespace App\Service;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Repository\MoodEntryRepository;
use App\Translation\MoodTranslationKeys;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Aggregated Pro reports: weekly series, aspect averages, mood distribution.
 */
class MoodReportService
{
    private const ALLOWED_RANGES = [30, 90];

    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function advanced(User $user, int $days = 30): array
    {
        $tier = SubscriptionTier::effectiveFor($user);
        if (!$tier->unlocksAdvancedReports()) {
            throw new AccessDeniedHttpException(MoodTranslationKeys::MOOD_REPORTS_LOCKED);
        }

        if (!\in_array($days, self::ALLOWED_RANGES, true)) {
            throw new BadRequestHttpException('mood.reports.range_invalid');
        }

        $to = (new \DateTimeImmutable('today'))->modify('+1 day');
        $from = $to->modify(sprintf('-%d days', $days));
        $entries = $this->moodEntryRepository->findBetween($user, $from, $to);
        $entryCount = \count($entries);

        $overallScores = array_map(static fn (MoodEntry $e) => $e->getOverallMood(), $entries);
        $averageOverall = $entryCount > 0
            ? round(array_sum($overallScores) / $entryCount, 2)
            : null;

        return [
            'unlocked' => true,
            'tier' => $tier->value,
            'rangeDays' => $days,
            'from' => $from->format('Y-m-d'),
            'to' => $to->modify('-1 day')->format('Y-m-d'),
            'entryCount' => $entryCount,
            'averageOverall' => $averageOverall,
            'weeklySeries' => $this->weeklySeries($entries, $from, $to),
            'aspectAverages' => $this->aspectAverages($entries),
            'moodDistribution' => $this->moodDistribution($overallScores),
            'bestDay' => $this->extremeDay($entries, true),
            'worstDay' => $this->extremeDay($entries, false),
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<MoodEntry> $entries
     *
     * @return list<array{weekStart: string, average: float|null, count: int}>
     */
    private function weeklySeries(array $entries, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $buckets = [];
        $cursor = $from->modify('monday this week')->setTime(0, 0);
        $end = $to->modify('-1 day');

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $buckets[$key] = ['sum' => 0.0, 'count' => 0];
            $cursor = $cursor->modify('+7 days');
        }

        foreach ($entries as $entry) {
            $created = $entry->getCreatedAt();
            if ($created === null) {
                continue;
            }
            $weekStart = $created->modify('monday this week')->format('Y-m-d');
            if (!isset($buckets[$weekStart])) {
                $buckets[$weekStart] = ['sum' => 0.0, 'count' => 0];
            }
            $buckets[$weekStart]['sum'] += $entry->getOverallMood();
            ++$buckets[$weekStart]['count'];
        }

        ksort($buckets);
        $series = [];
        foreach ($buckets as $weekStart => $bucket) {
            $series[] = [
                'weekStart' => $weekStart,
                'average' => $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 2) : null,
                'count' => $bucket['count'],
            ];
        }

        return $series;
    }

    /**
     * @param list<MoodEntry> $entries
     *
     * @return list<array{aspect: string, average: float, samples: int}>
     */
    private function aspectAverages(array $entries): array
    {
        $sums = [];
        $counts = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            $sums[$key] = 0.0;
            $counts[$key] = 0;
        }

        foreach ($entries as $entry) {
            foreach ($entry->getAspects() as $key => $value) {
                if (!isset($sums[$key]) || !isset($value['score'])) {
                    continue;
                }
                $sums[$key] += (int) $value['score'];
                ++$counts[$key];
            }
        }

        $rows = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            if ($counts[$key] === 0) {
                continue;
            }
            $rows[] = [
                'aspect' => $key,
                'average' => round($sums[$key] / $counts[$key], 2),
                'samples' => $counts[$key],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['average'] <=> $a['average']);

        return $rows;
    }

    /**
     * @param list<int> $scores
     *
     * @return list<array{score: int, count: int}>
     */
    private function moodDistribution(array $scores): array
    {
        $counts = array_fill(1, 6, 0);
        foreach ($scores as $score) {
            if ($score >= 1 && $score <= 6) {
                ++$counts[$score];
            }
        }

        $rows = [];
        foreach ($counts as $score => $count) {
            $rows[] = ['score' => (int) $score, 'count' => $count];
        }

        return $rows;
    }

    /**
     * @param list<MoodEntry> $entries
     *
     * @return array{date: string, score: int}|null
     */
    private function extremeDay(array $entries, bool $best): ?array
    {
        if ($entries === []) {
            return null;
        }

        $picked = $entries[0];
        foreach ($entries as $entry) {
            if ($best ? $entry->getOverallMood() > $picked->getOverallMood()
                : $entry->getOverallMood() < $picked->getOverallMood()) {
                $picked = $entry;
            }
        }

        return [
            'date' => $picked->getCreatedAt()?->format('Y-m-d') ?? '',
            'score' => $picked->getOverallMood(),
        ];
    }
}

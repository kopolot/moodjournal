<?php

namespace App\Service;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Enum\SubscriptionTier;
use App\Repository\MoodEntryRepository;
use App\Translation\MoodTranslationKeys;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Pattern-based mood analysis + coaching for Plus/Pro.
 * Deterministic (no external LLM required); optional narrative polish later.
 */
class MoodAnalysisService
{
    public const WINDOW_DAYS = 30;
    public const MIN_ENTRIES = 3;
    private const CACHE_TTL = 600;

    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
        private CacheInterface $moodCache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(User $user, bool $forceRefresh = false): array
    {
        $tier = $this->requireAiAccess($user);
        $cacheKey = $this->cacheKey($user);

        if ($forceRefresh) {
            $this->moodCache->delete($cacheKey);
        }

        /** @var array<string, mixed> $analysis */
        $analysis = $this->moodCache->get($cacheKey, function (ItemInterface $item) use ($user, $tier): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->buildAnalysis($user, $tier);
        });

        return $analysis;
    }

    public function invalidate(User $user): void
    {
        $this->moodCache->delete($this->cacheKey($user));
    }

    private function requireAiAccess(User $user): SubscriptionTier
    {
        $tier = SubscriptionTier::effectiveFor($user);
        if (!$tier->unlocksAi()) {
            throw new AccessDeniedHttpException(MoodTranslationKeys::MOOD_ANALYSIS_LOCKED);
        }

        return $tier;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnalysis(User $user, SubscriptionTier $tier): array
    {
        $to = (new \DateTimeImmutable('today'))->modify('+1 day');
        $from = $to->modify(sprintf('-%d days', self::WINDOW_DAYS));
        $entries = $this->moodEntryRepository->findBetween($user, $from, $to);
        $entryCount = \count($entries);

        if ($entryCount < self::MIN_ENTRIES) {
            return [
                'unlocked' => true,
                'tier' => $tier->value,
                'engine' => 'pattern',
                'windowDays' => self::WINDOW_DAYS,
                'entryCount' => $entryCount,
                'minEntries' => self::MIN_ENTRIES,
                'ready' => false,
                'summary' => null,
                'trend' => 'insufficient_data',
                'averageOverall' => null,
                'aspectInsights' => [],
                'coachingTips' => [
                    [
                        'id' => 'need_more_data',
                        'priority' => 'high',
                        'titleKey' => 'analysis.tip.needMore.title',
                        'bodyKey' => 'analysis.tip.needMore.body',
                        'params' => [
                            'needed' => self::MIN_ENTRIES - $entryCount,
                            'min' => self::MIN_ENTRIES,
                        ],
                    ],
                ],
                'highlights' => [],
                'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ];
        }

        $overallScores = array_map(static fn (MoodEntry $e) => $e->getOverallMood(), $entries);
        $averageOverall = round(array_sum($overallScores) / $entryCount, 2);
        $trend = $this->detectTrend($overallScores);
        $aspectInsights = $this->aspectInsights($entries);
        $volatility = $this->volatility($overallScores);
        $highlights = $this->highlights($entries, $aspectInsights, $trend, $volatility);
        $coachingTips = $this->coachingTips($aspectInsights, $trend, $volatility, $user->getCurrentStreak());

        return [
            'unlocked' => true,
            'tier' => $tier->value,
            'engine' => 'pattern',
            'windowDays' => self::WINDOW_DAYS,
            'entryCount' => $entryCount,
            'minEntries' => self::MIN_ENTRIES,
            'ready' => true,
            'summary' => [
                'headlineKey' => match ($trend) {
                    'improving' => 'analysis.summary.improving',
                    'declining' => 'analysis.summary.declining',
                    default => 'analysis.summary.stable',
                },
                'detailKey' => 'analysis.summary.detail',
                'params' => [
                    'average' => $averageOverall,
                    'entries' => $entryCount,
                    'days' => self::WINDOW_DAYS,
                    'volatility' => $volatility,
                ],
            ],
            'trend' => $trend,
            'averageOverall' => $averageOverall,
            'volatility' => $volatility,
            'aspectInsights' => $aspectInsights,
            'coachingTips' => $coachingTips,
            'highlights' => $highlights,
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<int> $scores chronological (oldest → newest)
     */
    private function detectTrend(array $scores): string
    {
        $n = \count($scores);
        if ($n < 4) {
            return 'stable';
        }

        $mid = (int) floor($n / 2);
        $first = array_slice($scores, 0, $mid);
        $second = array_slice($scores, $mid);
        $avgFirst = array_sum($first) / \count($first);
        $avgSecond = array_sum($second) / \count($second);
        $delta = $avgSecond - $avgFirst;

        if ($delta >= 0.4) {
            return 'improving';
        }
        if ($delta <= -0.4) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * @param list<MoodEntry> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function aspectInsights(array $entries): array
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

        $insights = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            if ($counts[$key] === 0) {
                continue;
            }
            $avg = round($sums[$key] / $counts[$key], 2);
            $insights[] = [
                'aspect' => $key,
                'average' => $avg,
                'samples' => $counts[$key],
                'status' => $avg >= 4.5 ? 'strength' : ($avg <= 3.0 ? 'focus' : 'neutral'),
            ];
        }

        usort($insights, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return $insights;
    }

    /**
     * @param list<int> $scores
     */
    private function volatility(array $scores): string
    {
        $n = \count($scores);
        if ($n < 2) {
            return 'low';
        }
        $mean = array_sum($scores) / $n;
        $variance = 0.0;
        foreach ($scores as $score) {
            $variance += ($score - $mean) ** 2;
        }
        $stdev = sqrt($variance / $n);

        if ($stdev >= 1.4) {
            return 'high';
        }
        if ($stdev >= 0.8) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param list<MoodEntry>            $entries
     * @param list<array<string, mixed>> $aspectInsights
     *
     * @return list<array<string, mixed>>
     */
    private function highlights(array $entries, array $aspectInsights, string $trend, string $volatility): array
    {
        $highlights = [];
        $strengths = array_values(array_filter($aspectInsights, static fn ($i) => $i['status'] === 'strength'));
        $focus = array_values(array_filter($aspectInsights, static fn ($i) => $i['status'] === 'focus'));

        if ($strengths !== []) {
            $best = $strengths[\count($strengths) - 1];
            $highlights[] = [
                'type' => 'strength',
                'titleKey' => 'analysis.highlight.strength',
                'params' => ['aspect' => $best['aspect'], 'average' => $best['average']],
            ];
        }
        if ($focus !== []) {
            $worst = $focus[0];
            $highlights[] = [
                'type' => 'focus',
                'titleKey' => 'analysis.highlight.focus',
                'params' => ['aspect' => $worst['aspect'], 'average' => $worst['average']],
            ];
        }

        $latest = $entries[\count($entries) - 1];
        $highlights[] = [
            'type' => 'latest',
            'titleKey' => 'analysis.highlight.latest',
            'params' => [
                'score' => $latest->getOverallMood(),
                'date' => $latest->getCreatedAt()?->format('Y-m-d'),
            ],
        ];

        if ($volatility === 'high') {
            $highlights[] = [
                'type' => 'volatility',
                'titleKey' => 'analysis.highlight.volatility',
                'params' => [],
            ];
        }

        if ($trend === 'improving') {
            $highlights[] = [
                'type' => 'trend',
                'titleKey' => 'analysis.highlight.trendUp',
                'params' => [],
            ];
        } elseif ($trend === 'declining') {
            $highlights[] = [
                'type' => 'trend',
                'titleKey' => 'analysis.highlight.trendDown',
                'params' => [],
            ];
        }

        return $highlights;
    }

    /**
     * @param list<array<string, mixed>> $aspectInsights
     *
     * @return list<array<string, mixed>>
     */
    private function coachingTips(array $aspectInsights, string $trend, string $volatility, int $streak): array
    {
        $tips = [];
        $focus = array_values(array_filter($aspectInsights, static fn ($i) => $i['status'] === 'focus'));
        $strengths = array_values(array_filter($aspectInsights, static fn ($i) => $i['status'] === 'strength'));

        if ($focus !== []) {
            $worst = $focus[0];
            $tips[] = [
                'id' => 'focus_aspect',
                'priority' => 'high',
                'titleKey' => 'analysis.tip.focusAspect.title',
                'bodyKey' => 'analysis.tip.focusAspect.body',
                'params' => ['aspect' => $worst['aspect'], 'average' => $worst['average']],
            ];
        }

        if ($trend === 'declining') {
            $tips[] = [
                'id' => 'trend_down',
                'priority' => 'high',
                'titleKey' => 'analysis.tip.trendDown.title',
                'bodyKey' => 'analysis.tip.trendDown.body',
                'params' => [],
            ];
        } elseif ($trend === 'improving') {
            $tips[] = [
                'id' => 'trend_up',
                'priority' => 'medium',
                'titleKey' => 'analysis.tip.trendUp.title',
                'bodyKey' => 'analysis.tip.trendUp.body',
                'params' => [],
            ];
        }

        if ($volatility === 'high') {
            $tips[] = [
                'id' => 'volatility',
                'priority' => 'medium',
                'titleKey' => 'analysis.tip.volatility.title',
                'bodyKey' => 'analysis.tip.volatility.body',
                'params' => [],
            ];
        }

        if ($strengths !== []) {
            $best = $strengths[\count($strengths) - 1];
            $tips[] = [
                'id' => 'lean_strength',
                'priority' => 'low',
                'titleKey' => 'analysis.tip.strength.title',
                'bodyKey' => 'analysis.tip.strength.body',
                'params' => ['aspect' => $best['aspect']],
            ];
        }

        if ($streak >= 3) {
            $tips[] = [
                'id' => 'streak',
                'priority' => 'low',
                'titleKey' => 'analysis.tip.streak.title',
                'bodyKey' => 'analysis.tip.streak.body',
                'params' => ['streak' => $streak],
            ];
        } else {
            $tips[] = [
                'id' => 'build_streak',
                'priority' => 'medium',
                'titleKey' => 'analysis.tip.buildStreak.title',
                'bodyKey' => 'analysis.tip.buildStreak.body',
                'params' => [],
            ];
        }

        usort($tips, static function (array $a, array $b): int {
            $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

            return ($rank[$a['priority']] ?? 9) <=> ($rank[$b['priority']] ?? 9);
        });

        return array_slice($tips, 0, 5);
    }

    private function cacheKey(User $user): string
    {
        return 'mood_analysis_' . ($user->getId()?->toRfc4122() ?? 'unknown');
    }
}

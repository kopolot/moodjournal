<?php

namespace App\Tests\Integration\Service;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Service\MoodAnalysisService;
use App\Service\OpenAiCompatibleClient;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class MoodAnalysisServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        TestDatabase::reset(static::getContainer());
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAnalyzeDeniedForFreeUser(): void
    {
        $user = $this->createUser('free');
        /** @var MoodAnalysisService $service */
        $service = static::getContainer()->get(MoodAnalysisService::class);

        $this->expectException(AccessDeniedHttpException::class);
        $service->analyze($user);
    }

    public function testAnalyzeReturnsPatternEngineWithoutLlm(): void
    {
        $user = $this->createUser('plus');
        $this->seedEntries($user, [3, 4, 5]);

        /** @var MoodAnalysisService $service */
        $service = static::getContainer()->get(MoodAnalysisService::class);
        $analysis = $service->analyze($user, true);

        $this->assertTrue($analysis['ready']);
        $this->assertSame('pattern', $analysis['engine']);
        $this->assertSame(3, $analysis['entryCount']);
        $this->assertNotEmpty($analysis['coachingTips']);
        $this->assertNull($analysis['narrative']);
    }

    public function testAnalyzeEnrichesNarrativeWhenLlmReturnsJson(): void
    {
        $user = $this->createUser('plus');
        $this->seedEntries($user, [3, 4, 5]);

        /** @var OpenAiCompatibleClient&MockObject $llm */
        $llm = $this->createMock(OpenAiCompatibleClient::class);
        $llm->method('isEnabled')->willReturn(true);
        $llm->method('chatJson')->willReturn([
            'headline' => 'Steady climb',
            'detail' => 'Your averages are improving across the window.',
            'tips' => ['Keep the bedtime routine.', 'Protect one quiet hour.'],
        ]);

        $service = new MoodAnalysisService(
            static::getContainer()->get(\App\Repository\MoodEntryRepository::class),
            static::getContainer()->get('cache.mood'),
            $llm,
        );

        $analysis = $service->analyze($user, true);

        $this->assertSame('pattern+llm', $analysis['engine']);
        $this->assertSame('Steady climb', $analysis['narrative']['headline']);
        $this->assertCount(2, $analysis['narrative']['tips']);
    }

    /**
     * @param list<int> $scores
     */
    private function seedEntries(User $user, array $scores): void
    {
        foreach ($scores as $i => $score) {
            $entry = new MoodEntry();
            $entry->setUser($user);
            $entry->setOverallMood($score);
            $entry->setNote('Seed note number ' . $i . ' for analysis.');
            $aspects = [];
            foreach (MoodEntry::ASPECT_KEYS as $key) {
                $aspects[$key] = ['score' => $score, 'note' => $key . ' note'];
            }
            $entry->setAspects($aspects);
            $entry->setXpEarned(10);
            $this->em->persist($entry);
        }
        $this->em->flush();
    }

    private function createUser(string $tier): User
    {
        $user = new User();
        $user
            ->setFirstname('Analysis')
            ->setEmail(sprintf('analysis.%s.%s@example.com', $tier, uniqid()))
            ->setPassword('hash')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setIsActive(true)
            ->setSubscriptionTier($tier);

        if ($tier !== 'free') {
            $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+30 days'));
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}

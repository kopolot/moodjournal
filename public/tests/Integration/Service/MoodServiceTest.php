<?php

namespace App\Tests\Integration\Service;

use App\Dto\MoodEntryDto;
use App\Entity\MoodEntry;
use App\Entity\User;
use App\Service\MoodService;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class MoodServiceTest extends KernelTestCase
{
    private MoodService $moodService;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        TestDatabase::reset(static::getContainer());
        $this->moodService = static::getContainer()->get(MoodService::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateRequiresAspectNotesForColdStart(): void
    {
        $user = $this->createUser();
        $dto = $this->buildMoodDto(withAspectNotes: false, withOverallNote: false);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage(\App\Translation\MoodTranslationKeys::MOOD_ASPECT_NOTE_REQUIRED);

        $this->moodService->create($user, $dto);
    }

    public function testCreateAwardsXpUpdatesStreakAndStats(): void
    {
        $user = $this->createUser();
        $dto = $this->buildMoodDto(withAspectNotes: true, withOverallNote: true);

        $entry = $this->moodService->create($user, $dto);
        /** @var User $freshUser */
        $freshUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        $this->assertInstanceOf(MoodEntry::class, $entry);
        $this->assertSame(94, $entry->getXpEarned());
        $this->assertNotNull($entry->getCreatedAt());
        $this->assertSame(94, $freshUser->getXpTotal());
        $this->assertSame(1, $freshUser->getCurrentStreak());
        $this->assertSame(1, $freshUser->getLongestStreak());
        $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $freshUser->getLastMoodDate()?->format('Y-m-d'));
        $this->assertCount(1, $this->entityManager->getRepository(MoodEntry::class)->findAll());

        $stats = $this->moodService->stats($freshUser);
        $this->assertSame(94, $stats['xpTotal']);
        $this->assertSame(1, $stats['level']);
        $this->assertSame(94, $stats['xpIntoLevel']);
    }

    public function testCreateContinuesExistingStreakAndAddsBonusXp(): void
    {
        $user = $this->createUser(
            currentStreak: 3,
            longestStreak: 3,
            lastMoodDate: new \DateTimeImmutable('yesterday')
        );
        $dto = $this->buildMoodDto(withAspectNotes: true, withOverallNote: true);

        $entry = $this->moodService->create($user, $dto);
        /** @var User $freshUser */
        $freshUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        $this->assertSame(100, $entry->getXpEarned());
        $this->assertSame(100, $freshUser->getXpTotal());
        $this->assertSame(4, $freshUser->getCurrentStreak());
        $this->assertSame(4, $freshUser->getLongestStreak());
    }

    public function testUpdateChangesOwnedEntryValues(): void
    {
        $user = $this->createUser();
        $entry = $this->createMoodEntry($user, overallMood: 4);

        $dto = $this->buildMoodDto(withAspectNotes: true, withOverallNote: true, overallMood: 6, aspectScore: 6);
        $dto->note = 'Updated integration note';

        $updated = $this->moodService->update($user, $entry->getId()?->toRfc4122() ?? '', $dto);

        $this->assertSame(6, $updated->getOverallMood());
        $this->assertSame('Updated integration note', $updated->getNote());
        $this->assertSame(6, $updated->getAspects()[MoodEntry::ASPECT_KEYS[0]]['score']);
    }

    public function testDeleteRemovesOwnedEntry(): void
    {
        $user = $this->createUser();
        $entry = $this->createMoodEntry($user, overallMood: 4);

        $this->moodService->delete($user, $entry->getId()?->toRfc4122() ?? '');

        $this->assertCount(0, $this->entityManager->getRepository(MoodEntry::class)->findAll());
    }

    public function testCheckinHintsDetectNoticeableDropFromPriorWindow(): void
    {
        $user = $this->createUser();

        $this->createMoodEntry(
            $user,
            overallMood: 6,
            createdAt: new \DateTimeImmutable('-14 days'),
            updatedAt: new \DateTimeImmutable('-14 days')
        );
        $this->createMoodEntry(
            $user,
            overallMood: 2,
            createdAt: new \DateTimeImmutable('-2 days'),
            updatedAt: new \DateTimeImmutable('-2 days')
        );

        $hints = $this->moodService->checkinHints($user);

        $this->assertTrue($hints['noticeableDrop']);
        $this->assertEquals(2.0, $hints['overallAverage']);
        $this->assertEquals(6.0, $hints['priorOverallAverage']);
        $this->assertArrayHasKey(MoodEntry::ASPECT_KEYS[0], $hints['aspectAverages']);
    }

    private function createUser(
        int $currentStreak = 0,
        int $longestStreak = 0,
        ?\DateTimeImmutable $lastMoodDate = null,
    ): User {
        $user = (new User())
            ->setFirstname('Mood')
            ->setEmail('mood.' . uniqid() . '@example.com')
            ->setPassword('hashed-password')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setIsActive(true)
            ->setCurrentStreak($currentStreak)
            ->setLongestStreak($longestStreak)
            ->setLastMoodDate($lastMoodDate);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createMoodEntry(
        User $user,
        int $overallMood,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ): MoodEntry {
        /** @var User $managedUser */
        $managedUser = $this->entityManager->getReference(User::class, $user->getId());

        $entry = (new MoodEntry())
            ->setUser($managedUser)
            ->setOverallMood($overallMood)
            ->setAspects($this->buildAspects(score: $overallMood, withNotes: true))
            ->setNote(sprintf('Mood note %d', $overallMood))
            ->setXpEarned(0);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        if ($createdAt !== null) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE mood_entry SET created_at = :createdAt WHERE hex(id) = :id',
                [
                    'createdAt' => $createdAt->format('Y-m-d H:i:s'),
                    'id' => strtoupper(str_replace('-', '', $entry->getId()?->toRfc4122() ?? '')),
                ]
            );
        }
        if ($updatedAt !== null) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE mood_entry SET updated_at = :updatedAt WHERE hex(id) = :id',
                [
                    'updatedAt' => $updatedAt->format('Y-m-d H:i:s'),
                    'id' => strtoupper(str_replace('-', '', $entry->getId()?->toRfc4122() ?? '')),
                ]
            );
        }
        if ($createdAt !== null || $updatedAt !== null) {
            $this->entityManager->clear(MoodEntry::class);
        }

        return $entry;
    }

    private function buildMoodDto(
        bool $withAspectNotes,
        bool $withOverallNote,
        int $overallMood = 4,
        int $aspectScore = 4,
    ): MoodEntryDto
    {
        $dto = new MoodEntryDto();
        $dto->overallMood = $overallMood;
        $dto->note = $withOverallNote ? 'Today felt balanced overall.' : null;
        $dto->aspects = $this->buildAspects($aspectScore, $withAspectNotes);

        return $dto;
    }

    /**
     * @return array<string, array{score: int, note: ?string}>
     */
    private function buildAspects(int $score, bool $withNotes): array
    {
        $aspects = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            $aspects[$key] = [
                'score' => $score,
                'note' => $withNotes ? sprintf('%s note', $key) : null,
            ];
        }

        return $aspects;
    }
}

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

    private function buildMoodDto(bool $withAspectNotes, bool $withOverallNote): MoodEntryDto
    {
        $dto = new MoodEntryDto();
        $dto->overallMood = 4;
        $dto->note = $withOverallNote ? 'Today felt balanced overall.' : null;
        $dto->aspects = [];

        foreach (MoodEntry::ASPECT_KEYS as $key) {
            $dto->aspects[$key] = [
                'score' => 4,
                'note' => $withAspectNotes ? sprintf('%s note', $key) : null,
            ];
        }

        return $dto;
    }
}

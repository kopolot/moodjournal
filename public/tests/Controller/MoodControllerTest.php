<?php

namespace App\Tests\Controller;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MoodControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        TestDatabase::reset(static::getContainer());
        self::ensureKernelShutdown();
    }

    public function testCreateMoodEntryReturnsEntryAndStats(): void
    {
        $client = static::createClient();
        $token = $this->createAuthenticatedUserAndLogin($client, 'mood-create');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/mood',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'overallMood' => 4,
                'note' => 'Today felt balanced overall.',
                'aspects' => $this->buildAspectPayload(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(\App\Translation\MoodTranslationKeys::MOOD_CREATED, $data['message'][0]);
        $this->assertSame(94, $data['data']['entry']['xpEarned']);
        $this->assertSame(94, $data['data']['stats']['xpTotal']);
        $this->assertSame(1, $data['data']['stats']['currentStreak']);
        $this->assertSame(1, $data['data']['stats']['entryCount']);
        $this->assertSame(4, $data['data']['entry']['overallMood']);
    }

    public function testListAndStatsReturnCreatedMoodEntries(): void
    {
        $client = static::createClient();
        $token = $this->createAuthenticatedUserAndLogin($client, 'mood-list');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/mood',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'overallMood' => 5,
                'note' => 'Good enough day with useful details.',
                'aspects' => $this->buildAspectPayload(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('GET', '/mood');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $listData = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($listData['success']);
        $this->assertCount(1, $listData['data']['items']);
        $this->assertSame(1, $listData['data']['total']);

        $client->request('GET', '/mood/stats');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $statsData = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($statsData['success']);
        $this->assertSame(94, $statsData['data']['xpTotal']);
        $this->assertSame(1, $statsData['data']['entryCount']);
        $this->assertEquals(5.0, $statsData['data']['averageOverall7d']);
    }

    public function testGetUpdateDeleteAndHintsFlowWorksForOwnedEntry(): void
    {
        $client = static::createClient();
        $token = $this->createAuthenticatedUserAndLogin($client, 'mood-flow');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/mood',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'overallMood' => 4,
                'note' => 'Today felt balanced overall.',
                'aspects' => $this->buildAspectPayload(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = json_decode($client->getResponse()->getContent(), true);
        $entryId = $created['data']['entry']['id'];

        $client->request('GET', '/mood/' . $entryId);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $getData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($entryId, $getData['data']['entry']['id']);

        $client->request(
            'PATCH',
            '/mood/' . $entryId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'overallMood' => 5,
                'note' => 'Updated mood note',
                'aspects' => $this->buildAspectPayload(score: 5),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(\App\Translation\MoodTranslationKeys::MOOD_UPDATED, $updated['message'][0]);
        $this->assertSame(5, $updated['data']['entry']['overallMood']);

        $client->request('GET', '/mood/checkin-hints');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $hints = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($hints['success']);
        $this->assertArrayHasKey('overallAverage', $hints['data']);
        $this->assertArrayHasKey('aspectAverages', $hints['data']);
        $this->assertFalse($hints['data']['noticeableDrop']);

        $client->request('DELETE', '/mood/' . $entryId);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $deleted = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(\App\Translation\MoodTranslationKeys::MOOD_DELETED, $deleted['message'][0]);

        $client->request('GET', '/mood/' . $entryId);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMoodEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/mood');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request(
            'POST',
            '/mood',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'overallMood' => 4,
                'note' => 'Unauthenticated request',
                'aspects' => $this->buildAspectPayload(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAnalysisRequiresPlusOrPro(): void
    {
        $client = static::createClient();
        $token = $this->createAuthenticatedUserAndLogin($client, 'mood-ai-locked');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request('GET', '/mood/analysis');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnalysisReturnsInsightsForPlusUser(): void
    {
        $client = static::createClient();
        $email = sprintf('mood-ai.%s@example.com', uniqid());
        $password = 'Test1234!';
        $this->createUser($email, $password, subscriptionTier: 'plus');

        $client->request(
            'POST',
            '/user/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );
        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['data']['jwt_token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        for ($i = 0; $i < 3; ++$i) {
            $client->request(
                'POST',
                '/mood',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'overallMood' => 3 + $i,
                    'note' => 'Analysis sample note number ' . $i,
                    'aspects' => $this->buildAspectPayload(score: 3 + ($i % 2)),
                ])
            );
            $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $client->request('GET', '/mood/analysis');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['ready']);
        $this->assertTrue($data['data']['unlocked']);
        $this->assertSame('pattern', $data['data']['engine']);
        $this->assertGreaterThanOrEqual(3, $data['data']['entryCount']);
        $this->assertNotEmpty($data['data']['coachingTips']);
        $this->assertArrayHasKey('aspectInsights', $data['data']);
    }

    /**
     * @return array<string, array{score: int, note: string}>
     */
    private function buildAspectPayload(int $score = 4): array
    {
        $payload = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            $payload[$key] = [
                'score' => $score,
                'note' => sprintf('%s note', $key),
            ];
        }

        return $payload;
    }

    private function createAuthenticatedUserAndLogin(object $client, string $prefix): string
    {
        $email = sprintf('%s.%s@example.com', $prefix, uniqid());
        $password = 'Test1234!';
        $this->createUser($email, $password);

        $client->request(
            'POST',
            '/user/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
            ])
        );
        $loginData = json_decode($client->getResponse()->getContent(), true);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return $loginData['data']['jwt_token'];
    }

    private function createUser(string $email, string $password, string $subscriptionTier = 'free'): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user
            ->setFirstname('Mood')
            ->setEmail($email)
            ->setPassword($passwordHasher->hashPassword($user, $password))
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setIsActive(true)
            ->setSubscriptionTier($subscriptionTier);

        if ($subscriptionTier !== 'free') {
            $user->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+30 days'));
        }

        $entityManager->persist($user);
        $entityManager->flush();
    }
}

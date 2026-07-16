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

    /**
     * @return array<string, array{score: int, note: string}>
     */
    private function buildAspectPayload(): array
    {
        $payload = [];
        foreach (MoodEntry::ASPECT_KEYS as $key) {
            $payload[$key] = [
                'score' => 4,
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

    private function createUser(string $email, string $password): void
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
            ->setIsActive(true);

        $entityManager->persist($user);
        $entityManager->flush();
    }
}

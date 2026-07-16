<?php

namespace App\Tests\Integration\LocalLlm;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Tests\Support\LocalLlm;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Spatie\Async\Pool;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Real end-to-end check against local Ollama (compose profile `llm`).
 *
 * Skipped automatically when Ollama/model is unavailable so default CI stays green.
 *
 * Run explicitly:
 *   docker exec mood_dic-php-1 php bin/phpunit --group local-llm
 *
 * @group local-llm
 */
final class MoodAnalysisLocalLlmTest extends WebTestCase
{
    protected function setUp(): void
    {
        $probe = LocalLlm::probe();
        if (!$probe['ok']) {
            $this->markTestSkipped($probe['reason'] ?? 'Local LLM unavailable');
        }

        LocalLlm::enableEnvForSymfony();

        self::ensureKernelShutdown();
        self::bootKernel();
        TestDatabase::reset(static::getContainer());
        self::ensureKernelShutdown();
    }

    public function testAnalysisHttpEndpointReturnsPatternPlusLlmNarrative(): void
    {
        $client = static::createClient();
        $email = sprintf('local-llm.%s@example.com', uniqid());
        $password = 'Test1234!';
        $this->createPlusUser($email, $password);

        $client->request(
            'POST',
            '/user/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $login = json_decode($client->getResponse()->getContent(), true);
        $token = $login['data']['jwt_token'];
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        for ($i = 0; $i < 3; ++$i) {
            // No Idempotency-Key: PHPUnit bootstrap points Redis at 127.0.0.1,
            // which is unavailable inside the PHP container.
            $client->request(
                'POST',
                '/mood',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'overallMood' => 3 + $i,
                    'note' => 'Local LLM integration seed note ' . $i,
                    'aspects' => $this->buildAspectPayload(3 + ($i % 2)),
                ], JSON_THROW_ON_ERROR)
            );
            $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $client->request('GET', '/mood/analysis?refresh=1&lang=pl');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($payload['success'] ?? false);
        $data = $payload['data'] ?? [];

        $this->assertTrue($data['ready'] ?? false);
        $this->assertSame('pl', $data['locale'] ?? null);
        $this->assertSame('pattern+llm', $data['engine'] ?? null, 'Expected real local LLM enrichment, got: ' . json_encode($data));
        $this->assertGreaterThanOrEqual(3, $data['entryCount'] ?? 0);
        $this->assertIsArray($data['narrative'] ?? null);
        $this->assertNotEmpty($data['narrative']['headline'] ?? null);
        $this->assertNotEmpty($data['narrative']['detail'] ?? null);
        $this->assertIsArray($data['narrative']['tips'] ?? null);
        $this->assertNotEmpty($data['narrative']['tips']);
        $this->assertNotEmpty($data['coachingTips'] ?? []);

        $blob = strtolower(
            ($data['narrative']['headline'] ?? '')
            . ' '
            . ($data['narrative']['detail'] ?? '')
            . ' '
            . implode(' ', $data['narrative']['tips'] ?? [])
        );
        $this->assertMatchesRegularExpression(
            '/[ąćęłńóśźż]|stabil|nastro|obszar|nawyk|fokus|finanse|otoczen/u',
            $blob,
            'Expected Polish narrative cues, got: ' . $blob
        );
    }

    /**
     * @return array<string, array{score: int, note: string}>
     */
    private function buildAspectPayload(int $score): array
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

    private function createPlusUser(string $email, string $password): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user
            ->setFirstname('LocalLlm')
            ->setEmail($email)
            ->setPassword($hasher->hashPassword($user, $password))
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true)
            ->setIsActive(true)
            ->setSubscriptionTier('plus')
            ->setSubscriptionExpiresAt((new \DateTimeImmutable('now'))->modify('+30 days'));

        $em->persist($user);
        $em->flush();
    }
}

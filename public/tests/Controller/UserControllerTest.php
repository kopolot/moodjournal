<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        TestDatabase::reset(static::getContainer());
        self::ensureKernelShutdown();
    }

    public function testUserRegistrationCreatesUserAndQueuesVerificationEmail(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $firstname = 'Test';
        $email = 'test.user' . uniqid() . '@example.com';
        $password = 'Test1234!';

        $client->request(
            'POST',
            '/user/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstname' => $firstname,
                'email' => $email,
                'password' => $password,
                'repeatPassword' => $password,
                'acceptPrivacyPolicy' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertContains(\App\Translation\UserTranslationKeys::USER_REGISTRATION_SUCCESS, $responseData['message']);

        $userRepository = $entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->findOneBy(['email' => $email]);

        $this->assertNotNull($user);
        $this->assertEquals($firstname, $user->getFirstname());
        $this->assertEquals($email, $user->getEmail());
        $this->assertFalse($user->isVerified());
        $this->assertFalse($user->isActive());
        $this->assertNotEquals($password, $user->getPassword());

        $this->assertEmailCount(1);
        $message = $this->getMailerMessage();
        $this->assertEmailHeaderSame($message, 'To', $user->getEmail());

        $verificationToken = $user->getVerificationToken();
        $this->assertNotNull($verificationToken);
        $this->assertEmailTextBodyContains($message, $verificationToken);
    }

    public function testVerifyEndpointActivatesUser(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createUser(
            email: 'verify.' . uniqid() . '@example.com',
            password: 'Test1234!',
            isVerified: false,
            isActive: false,
            verificationToken: 'verify-token'
        );

        $client->request('GET', '/user/verify/verify-token', ['format' => 'json'], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $entityManager->clear();
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->find($user->getId());
        $this->assertTrue($user->isVerified());
        $this->assertTrue($user->isActive());
    }

    public function testUserLoginReturnsJwtToken(): void
    {
        $client = static::createClient();
        $email = 'login.' . uniqid() . '@example.com';
        $password = 'Test1234!';

        $this->createUser(
            email: $email,
            password: $password,
            isVerified: true,
            isActive: true
        );

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

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(\App\Translation\UserTranslationKeys::USER_LOGIN_SUCCESS, $responseData['message'][0]);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('jwt_token', $responseData['data']);
        $this->assertIsString($responseData['data']['jwt_token']);
        $this->assertNotSame('', $responseData['data']['jwt_token']);
    }

    public function testGetUserDataReturnsAuthenticatedProfile(): void
    {
        $client = static::createClient();
        $email = 'profile.' . uniqid() . '@example.com';
        $password = 'Test1234!';

        $this->createUser(
            email: $email,
            password: $password,
            isVerified: true,
            isActive: true
        );

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
        $jwtToken = $loginData['data']['jwt_token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $jwtToken);
        $client->request('GET', '/user/get');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertSame($email, $responseData['data']['email']);
        $this->assertSame('Test', $responseData['data']['firstname']);
        $this->assertArrayHasKey('xpTotal', $responseData['data']);
        $this->assertArrayHasKey('currentStreak', $responseData['data']);
    }

    private function createUser(
        string $email,
        string $password,
        bool $isVerified,
        bool $isActive,
        ?string $verificationToken = null,
    ): User {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user
            ->setFirstname('Test')
            ->setEmail($email)
            ->setPassword($passwordHasher->hashPassword($user, $password))
            ->setRoles(['ROLE_USER'])
            ->setIsVerified($isVerified)
            ->setIsActive($isActive)
            ->setVerificationToken($verificationToken);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
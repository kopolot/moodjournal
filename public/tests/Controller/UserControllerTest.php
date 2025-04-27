<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class UserControllerTest extends WebTestCase
{
    /**
     * @test
     */
    public function testUserRegistration(): array
    {
        $client = static::createClient();
        $router = static::getContainer()->get(RouterInterface::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Przygotuj dane testowe
        $firstName = 'Test';
        $email = 'test.user' . uniqid() . '@example.com';
        $password = 'Test1234!';
        
        // Wykonaj żądanie rejestracji
        $client->request(
            'POST',
            $router->generate('user.registration'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => $firstName,
                'email' => $email,
                'password' => $password,
                'repeatPassword' => $password,
                'acceptPrivacyPolicy' => true
            ])
        );

        // Sprawdź kod odpowiedzi
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        // Sprawdź strukturę odpowiedzi JSON
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertContains(\App\Translation\UserTranslationKeys::USER_REGISTRATION_SUCCESS, $responseData['message']);
        
        // Sprawdź czy użytkownik został utworzony w bazie danych
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);
        
        $this->assertNotNull($user);
        /** @var User $user */
        $this->assertEquals($firstName, $user->getFirstName());
        $this->assertEquals($email, $user->getEmail());
        
        // Sprawdź czy hasło jest zahaszowane
        $this->assertNotEquals($password, $user->getPassword());
        
        // Sprawdź czy email weryfikacyjny został wysłany
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        
        // Sprawdź właściwości emaila
        $this->assertEmailHeaderSame($email, 'To', $user->getEmail());
        
        // Pobierz token weryfikacyjny
        $verificationToken = $user->getVerificationToken();
        $this->assertNotNull($verificationToken);
        
        // Sprawdź czy token jest w treści emaila
        $this->assertEmailTextBodyContains($email, $verificationToken);
        
        $activationUrl = $router->generate('user.verify', ['token' => $verificationToken]);
        $client->request('GET', $activationUrl);
        $this->assertResponseIsSuccessful();
        
        // $entityManager->clear();
        // Sprawdź czy konto zostało zweryfikowane
        $user = $userRepository->find( $user->getId());
        $this->assertTrue($user->isVerified());
        
        return [
            'email' => $user->getEmail(),
            'password' => $password
        ];
    }

    /**
     * @depends testUserRegistration
     * @test
     */
    public function testUserLogin( array $userData): string{
        $client = static::createClient();
        $router = static::getContainer()->get('router');
        $client->request(
            'POST',
            $router->generate('user.login'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode( $userData)
        );
        $this->assertResponseStatusCodeSame( Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals( \App\Translation\UserTranslationKeys::USER_LOGIN_SUCCESS, $responseData['message'][0]);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('jwt_token', $responseData['data']);
        $jwtToken = $responseData['data']['jwt_token'];
        return $jwtToken;
    }
    
    /**
     * @depends testUserLogin
     */
    public function testGetUserData( string $jwtToken)
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $jwtToken);
        $client->request('GET', '/user/get');
        
        $this->assertResponseStatusCodeSame( Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        echo \PHP_EOL;
        \var_dump($responseData);
    }
}
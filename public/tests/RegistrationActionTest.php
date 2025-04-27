<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegistrationActionTest extends WebTestCase
{
    public function __construct(
        protected UserRepository $userRepository
    )
    {}

    public function testUserRegistration(): void
    {
        $client = static::createClient();
        
        // Przygotuj dane testowe
        $firstName = 'Test';
        $email = 'test.registration' . uniqid() . '@example.com';
        $password = 'Test1234!';
        
        // Wykonaj żądanie rejestracji
        $client->request(
            'POST',
            '/user/register',
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
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        
        // Sprawdź strukturę odpowiedzi JSON
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertContains(\App\Translation\UserTranslationKeys::USER_REGISTRATION_SUCCESS, $responseData['message']);
        
        // Sprawdź czy użytkownik został utworzony w bazie danychz
        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        $this->assertNotNull($user);
        /** @var User $user */
        $this->assertEquals($firstName, $user->getFirstName());
        $this->assertEquals($email, $user->getEmail());
        
        // Sprawdź czy hasło jest zahaszowane (nie przechowywane jako plain text)
        $this->assertNotEquals($password, $user->getPassword());
    }
    
    // public function testInvalidRegistrationData(): void
    // {
    //     $client = static::createClient();
        
    //     // Test bez wymaganych pól
    //     $client->request(
    //         'POST',
    //         '/api/register', // Dostosuj ścieżkę
    //         [],
    //         [],
    //         ['CONTENT_TYPE' => 'application/json'],
    //         json_encode([
    //             'email' => 'invalid-email', // Nieprawidłowy format emaila
    //             'password' => '123', // Za krótkie hasło
    //         ])
    //     );
        
    //     $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        
    //     $responseData = json_decode($client->getResponse()->getContent(), true);
    //     $this->assertFalse($responseData['success']);
    //     $this->assertArrayHasKey('error', $responseData);
    // }
    
    // public function testDuplicateEmail(): void
    // {
    //     $client = static::createClient();
    //     $email = 'duplicate.test' . uniqid() . '@example.com';
    //     $password = 'Test1234!';
    //     $username = 'duplicateuser_' . uniqid();
        
    //     // Pierwsze żądanie - poprawna rejestracja
    //     $client->request(
    //         'POST',
    //         '/api/register',
    //         [],
    //         [],
    //         ['CONTENT_TYPE' => 'application/json'],
    //         json_encode([
    //             'email' => $email,
    //             'password' => $password,
    //             'username' => $username
    //         ])
    //     );
        
    //     $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        
    //     // Drugie żądanie - ten sam email
    //     $client->request(
    //         'POST',
    //         '/api/register',
    //         [],
    //         [],
    //         ['CONTENT_TYPE' => 'application/json'],
    //         json_encode([
    //             'email' => $email,
    //             'password' => $password,
    //             'username' => 'another_' . $username
    //         ])
    //     );
        
    //     $this->assertEquals(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
        
    //     $responseData = json_decode($client->getResponse()->getContent(), true);
    //     $this->assertFalse($responseData['success']);
    //     $this->assertArrayHasKey('error', $responseData);
    // }
}
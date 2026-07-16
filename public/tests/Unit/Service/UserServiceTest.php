<?php

namespace App\Tests\Unit\Service;

use App\Dto\UserDto;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Service\UserService;
use App\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class UserServiceTest extends \PHPUnit\Framework\TestCase
{
    private UserService $userService;
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->willReturnMap(
                [
                    ['mailer.from_address', 'test@example.com'],
                    ['app.url', 'http://localhost'],
                ]
            );

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->userService = new UserService(
            $this->userRepository,
            $this->passwordHasher,
            $this->eventDispatcher,
            $this->createMock(MailerInterface::class),
            $parameterBag,
            $this->createMock(Environment::class),
            $this->createMock(TranslatorInterface::class),
        );
    }

    public function testRegisterPersistsUserAndDispatchesRegisteredEvent(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);
        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getEmail() === 'test@example.com'
                    && $user->getFirstname() === 'Test'
                    && $user->getPassword() === 'hashed_password'
                    && $user->isVerified() === false
                    && $user->isActive() === false
                    && $user->getVerificationToken() !== null
                    && $user->getRoles() === ['ROLE_USER'];
            }));

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (UserRegisteredEvent $event): bool {
                    return $event->getUser()->getEmail() === 'test@example.com';
                }),
                UserRegisteredEvent::class
            )
            ->willReturnCallback(static function (object $event): object {
                return $event;
            });

        $userDto = new UserDto();
        $userDto->email = 'test@example.com';
        $userDto->password = 'password';
        $userDto->repeatPassword = 'password';
        $userDto->firstname = 'Test';

        $user = $this->userService->register($userDto);

        $this->assertInstanceOf(\App\Entity\User::class, $user);
        $this->assertEquals('Test', $user->getFirstname());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('hashed_password', $user->getPassword());
        $this->assertFalse($user->isVerified());
        $this->assertFalse($user->isActive());
        $this->assertNotEmpty($user->getVerificationToken());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testVerifyUserActivatesExistingAccount(): void
    {
        $user = (new User())
            ->setFirstname('Test')
            ->setEmail('test@example.com')
            ->setPassword('hashed_password')
            ->setIsVerified(false)
            ->setIsActive(false)
            ->setVerificationToken('verify-token');
        (new \ReflectionProperty($user, 'id'))->setValue($user, Uuid::v4());

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['verificationToken' => 'verify-token'])
            ->willReturn($user);

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($user);

        $this->userService->verifyUser('verify-token');

        $this->assertTrue($user->isVerified());
        $this->assertTrue($user->isActive());
    }
}
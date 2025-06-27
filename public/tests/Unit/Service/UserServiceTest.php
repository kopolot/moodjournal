<?php

namespace App\Tests\Unit\Service;

use ReflectionProperty;
use App\Service\UserService;
use Symfony\Component\Uid\Uuid;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class UserServiceTest extends \PHPUnit\Framework\TestCase
{
    private UserService $userService;

    protected function setUp(): void{
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->willReturnMap(
                [
                    [ 'mailer.from_address', 'test@example.com']
                ]
            );
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $this->userService = new UserService(
            $this->createMock(UserRepository::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $eventDispatcher,
            $this->createMock(MailerInterface::class),
            $parameterBag
        );
    }

    public function testRegister(){
        $uuid = \uuid_create();
        $userRepository = ( new \ReflectionProperty($this->userService, 'userRepository'))
            ->getValue( $this->userService);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);
        $userRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function($user) use ($uuid) {
                $uuid_mock = $this->createMock( Uuid::class);
                $uuid_mock->method('toRfc4122')
                    ->willReturn($uuid);
                (new \ReflectionProperty($user, 'id'))
                    ->setValue(
                        $user,
                        $uuid_mock
                    );
            });
        $passwordHasher = ( new \ReflectionProperty($this->userService, 'passwordHasher'))
            ->getValue( $this->userService);
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');
        $userDto = $this->createMock(\App\Dto\UserDto::class);
        $userDto->email = 'test@example.com';
        $userDto->password = 'password';
        $userDto->firstname = 'Test';
        $user = $this->userService->register($userDto);
        $this->assertInstanceOf(\App\Entity\User::class, $user);
        $this->assertEquals('Test', $user->getFirstName());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('hashed_password', $user->getPassword());
        $this->assertFalse($user->isVerified());
        $this->assertFalse($user->isActive());
        $this->assertNotEmpty($user->getVerificationToken());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
        $this->assertEquals($uuid, $user->getId()->toRfc4122());
    }
}
<?php

namespace App\Service;

use App\Dto\UserDto;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Exception\UserAlreadyExistsException;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected UserPasswordHasherInterface $passwordHasher,
        protected EventDispatcherInterface $eventDispatcher,
    ){}

    public function register( UserDto $userDto): User{
        if ( $this->userRepository->findByEmail($userDto->email)) {
            throw new UserAlreadyExistsException;
        }

        // before registration event

        $user = new User();
        $user->setFirstName($userDto->firstName);
        $user->setEmail($userDto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        $user->setIsActive(true);
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user), 'user.registered');

        return $user;
    }
}

<?php

namespace App\Service;

use App\Dto\UserRegistrationDto;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function register(UserRegistrationDto $userDto): User
    {
        if ($this->userRepository->findByEmail($userDto->email)) {
            // throw new Translate;
            // error
        }

        $user = new User();
        $user->setFirstName($userDto->firstName);
        $user->setEmail($userDto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        $user->setIsActive(true);
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));

        return $user;
    }
}

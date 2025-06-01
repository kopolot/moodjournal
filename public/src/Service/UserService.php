<?php

namespace App\Service;

use App\Dto\UserDto;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Exception\UserAlreadyExistsException;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        $user->setFirstName($userDto->firstname);
        $user->setEmail($userDto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        $user->setIsActive(false);
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user), 'user.registered');

        return $user;
    }

    public function verifyUser( string $token): void{
        $user = $this->userRepository->findOneBy([ 'verificationToken' => $token]);
        if( !$user || !$user->getId()) {
            throw new NotFoundHttpException(
                \App\Translation\UserTranslationKeys::USER_NOT_FOUND,
            );
        }
        if( $user->isVerified()) {
            throw new ConflictHttpException(
                \App\Translation\UserTranslationKeys::USER_VERIFY_ALREADY,
            );
        }
        $user->setIsVerified(true);
        $user->setIsActive(true);
        $this->userRepository->save($user);
    }

    public function resetPassword( UserDto $userDto){
        $user = $this->userRepository->findByEmail($userDto->email);
        if( !$user || !$user->getId()) {
            throw new NotFoundHttpException(
                \App\Translation\UserTranslationKeys::USER_NOT_FOUND,
            );
        }
        if( $user->isVerified()) {
            throw new ConflictHttpException(
                \App\Translation\UserTranslationKeys::USER_VERIFY_ALREADY,
            );
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $this->userRepository->save($user);
    }
}

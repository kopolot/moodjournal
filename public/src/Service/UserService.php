<?php

namespace App\Service;

use App\Dto\UserDto;
use App\Entity\User;
use App\Event\UserDisabledEvent;
use Symfony\Component\Mime\Email;
use App\Event\UserRegisteredEvent;
use App\Repository\UserRepository;
use App\Exception\UserAlreadyExistsException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UserService
{

    protected string $mailerFromAddress;

    public function __construct(
        protected UserRepository $userRepository,
        protected UserPasswordHasherInterface $passwordHasher,
        protected EventDispatcherInterface $eventDispatcher,
        protected MailerInterface $mailer,
        protected ParameterBagInterface $parameterBag,
    ) {
        $this->mailerFromAddress = $this->parameterBag->get('mailer.from_address');
    }

    public function register(UserDto $userDto): User
    {
        if ($this->userRepository->findByEmail($userDto->email)) {
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
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user),  UserRegisteredEvent::class);

        return $user;
    }

    public function verifyUser(string $token): void
    {
        $user = $this->userRepository->findOneBy(['verificationToken' => $token]);
        if (!$user || !$user->getId()) {
            throw new NotFoundHttpException(
                \App\Translation\UserTranslationKeys::USER_NOT_FOUND,
            );
        }
        if ($user->isVerified()) {
            throw new ConflictHttpException(
                \App\Translation\UserTranslationKeys::USER_VERIFY_ALREADY,
            );
        }
        $user->setIsVerified(true);
        $user->setIsActive(true);
        $this->userRepository->save($user);
    }

    public function resetPassword(UserDto $userDto)
    {
        $user = $this->userRepository->findByEmail($userDto->email);
        if (!$user || !$user->getId()) {
            throw new NotFoundHttpException(
                \App\Translation\UserTranslationKeys::USER_NOT_FOUND,
            );
        }
        if ($user->isVerified()) {
            throw new ConflictHttpException(
                \App\Translation\UserTranslationKeys::USER_VERIFY_ALREADY,
            );
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->password));
        $this->userRepository->save($user);
    }

    public function sendResetPasswordEmail(UserDto $userDto): void
    {
        $user = $this->userRepository->findByEmail($userDto->email);
        if (!$user || !$user->getId()) {
            throw new NotFoundHttpException(
                \App\Translation\UserTranslationKeys::USER_NOT_FOUND,
            );
        }
    }

    public function sendEmailToUser(User $user, string $subject, string $htmlContent, string $textContent): void
    {
        $email = new Email();
        $email->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject($subject)
            ->text($textContent)
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    public function disableUser(User $user): void
    {
        $user->setIsActive(false);
        $this->userRepository->save($user);
        // user disabled event
        $this->eventDispatcher->dispatch(new UserDisabledEvent($user), UserDisabledEvent::class);
    }

    public function deleteDisabledUser(User $user): void
    {
        if (!$user->getId()) {
            throw new NotFoundHttpException(
                \App\Translation\UserTranslationKeys::USER_NOT_FOUND,
            );
        }
        $this->userRepository->delete($user);
        $this->sendEmailToUser(
            $user,
            \App\Translation\UserTranslationKeys::USER_EMAIL_ACCOUNT_DELETED_SUBJECT,
            'Your account has been deleted. Placeholder for HTML content.',
            'Your account has been deleted. Placeholder for text content.'
        );
    }

    public function editUser(User $user, UserDto $userDto): void
    {
        if (isset($userDto->firstname)) {
            $user->setFirstName($userDto->firstname);
        }
        if ($userDto?->preferences) {
            $user->setPreferences($userDto->preferences->__serialize());
        }
        $this->userRepository->save($user);
    }
}

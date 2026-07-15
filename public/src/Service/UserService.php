<?php

namespace App\Service;

use App\Dto\UserDto;
use App\Dto\UserPreferencesPatchDto;
use App\Entity\User;
use App\Event\UserDisabledEvent;
use App\Event\UserRegisteredEvent;
use App\Exception\UserAlreadyExistsException;
use App\Repository\UserRepository;
use App\Translation\UserTranslationKeys;
use App\ValueObject\UserPreferences;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class UserService
{
    protected string $mailerFromAddress;
    protected string $appUrl;

    public function __construct(
        protected UserRepository $userRepository,
        protected UserPasswordHasherInterface $passwordHasher,
        protected EventDispatcherInterface $eventDispatcher,
        protected MailerInterface $mailer,
        protected ParameterBagInterface $parameterBag,
        protected Environment $twig,
        protected TranslatorInterface $translator,
    ) {
        $this->mailerFromAddress = $this->parameterBag->get('mailer.from_address');
        $this->appUrl = rtrim((string) $this->parameterBag->get('app.url'), '/');
    }

    public function register(UserDto $userDto): User
    {
        if ($this->userRepository->findByEmail($userDto->email)) {
            throw new UserAlreadyExistsException();
        }

        $user = new User();
        $user->setFirstName((string) $userDto->firstname);
        $user->setEmail((string) $userDto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $userDto->password));
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        $user->setIsActive(false);
        $user->setVerificationToken(bin2hex(random_bytes(32)));

        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user), UserRegisteredEvent::class);

        return $user;
    }

    public function verifyUser(string $token): void
    {
        $user = $this->userRepository->findOneBy(['verificationToken' => $token]);
        if (!$user || !$user->getId()) {
            throw new NotFoundHttpException(UserTranslationKeys::USER_NOT_FOUND);
        }
        if ($user->isVerified()) {
            throw new ConflictHttpException(UserTranslationKeys::USER_VERIFY_ALREADY);
        }
        $user->setIsVerified(true);
        $user->setIsActive(true);
        $this->userRepository->save($user);
    }

    /**
     * Always succeeds from the caller's perspective when email is missing —
     * avoids account enumeration. Sends mail only for known verified users.
     */
    public function sendResetPasswordEmail(UserDto $userDto): void
    {
        $user = $this->userRepository->findByEmail($userDto->email);
        if (!$user || !$user->getId() || !$user->isVerified()) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetPasswordToken($token);
        $this->userRepository->save($user);

        $htmlContent = $this->twig->render('emails/user/forgetpassword.html.twig', [
            'user' => $user,
            'resetToken' => $token,
            'appUrl' => $this->appUrl,
        ]);
        $textContent = $this->twig->render('emails/user/forgetpassword.txt.twig', [
            'user' => $user,
            'resetToken' => $token,
            'appUrl' => $this->appUrl,
        ]);

        $this->sendEmailToUser(
            $user,
            $this->translator->trans(UserTranslationKeys::USER_EMAIL_FORGOT_PASSWORD_SUBJECT),
            $htmlContent,
            $textContent
        );
    }

    public function resetPassword(UserDto $userDto): void
    {
        $token = trim((string) $userDto->token);
        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $token]);
        if (!$user || !$user->getId()) {
            throw new NotFoundHttpException(UserTranslationKeys::USER_RESET_TOKEN_INVALID);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $userDto->password));
        $user->setResetPasswordToken(null);
        if (!$user->isActive()) {
            $user->setIsActive(true);
        }
        $this->userRepository->save($user);
    }

    public function changePassword(User $user, UserDto $userDto): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, (string) $userDto->currentPassword)) {
            throw new UnprocessableEntityHttpException(UserTranslationKeys::USER_CURRENT_PASSWORD_INVALID);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $userDto->password));
        $this->userRepository->save($user);
    }

    public function findByResetToken(string $token): ?User
    {
        return $this->userRepository->findOneBy(['resetPasswordToken' => $token]);
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
        $this->eventDispatcher->dispatch(new UserDisabledEvent($user), UserDisabledEvent::class);
    }

    public function deleteDisabledUser(User $user): void
    {
        if (!$user->getId()) {
            throw new NotFoundHttpException(UserTranslationKeys::USER_NOT_FOUND);
        }
        $this->userRepository->delete($user);
        $this->sendEmailToUser(
            $user,
            UserTranslationKeys::USER_EMAIL_ACCOUNT_DELETED_SUBJECT,
            'Your account has been deleted. Placeholder for HTML content.',
            'Your account has been deleted. Placeholder for text content.'
        );
    }

    public function editUser(User $user, UserDto $userDto): void
    {
        if ($userDto->firstname !== null) {
            $user->setFirstName($userDto->firstname);
        }
        if ($userDto->preferences !== null) {
            $user->setPreferences($this->mergePreferences($user, $userDto->preferences));
        }
        $this->userRepository->save($user);
    }

    /**
     * @return array{language: string, darkMode: bool, dailyNotifications: bool}
     */
    private function mergePreferences(User $user, UserPreferencesPatchDto $patch): array
    {
        $current = new UserPreferences();
        $current->__unserialize($user->getPreferences() ?? []);

        if ($patch->language !== null) {
            $current->setLanguage($patch->language);
        }
        if ($patch->darkMode !== null) {
            $current->setDarkMode($patch->darkMode);
        }
        if ($patch->dailyNotifications !== null) {
            $current->setDailyNotifications($patch->dailyNotifications);
        }

        return $current->__serialize();
    }
}

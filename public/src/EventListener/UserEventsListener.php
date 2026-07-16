<?php

namespace App\EventListener;

use App\Entity\User;
use Twig\Environment;
use App\Service\UserService;
use App\Event\UserDisabledEvent;
use Symfony\Component\Mime\Email;
use App\Event\UserRegisteredEvent;
use App\Translation\UserTranslationKeys;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use App\Security\Authenticator\Passport\Credentials\IsActiveCredential;

class UserEventsListener
{

    public function __construct(
        protected MailerInterface $mailer,
        protected Environment $twig,
        protected TranslatorInterface $translator,
        #[Autowire('%mailer.from_address%')]
        protected string $mailerFromAddress,
        #[Autowire('%app.url%')]
        protected string $appUrl,
        protected UserService $userService,
    ) {}

    #[AsEventListener(UserRegisteredEvent::class, priority: 100)]
    public function onUserRegistered(UserRegisteredEvent $event)
    {
        $user = $event->getUser();

        $htmlContent = $this->twig->render('emails/user/registration.html.twig', [
            'user' => $user,
            'verificationToken' => $user->getVerificationToken(),
            'appUrl' => $this->appUrl,
        ]);

        $textContent = $this->twig->render('emails/user/registration.txt.twig', [
            'user' => $user,
            'verificationToken' => $user->getVerificationToken(),
            'appUrl' => $this->appUrl,
        ]);

        $this->userService->sendEmailToUser(
            $user,
            $this->translator->trans(UserTranslationKeys::USER_EMAIL_REGISTRATION_SUBJECT),
            $htmlContent,
            $textContent
        );
    }

    #[AsEventListener(CheckPassportEvent::class, priority: 100)]
    public function checkPassport(CheckPassportEvent $event)
    {
        $passport = $event->getPassport();
        if ($passport->hasBadge(IsActiveCredential::class)) {
            /** @var User $user */
            $user = $passport->getUser();
            if (!$user->isActive()) {
                throw new LockedHttpException(UserTranslationKeys::USER_ACCOUNT_INACTIVE);
            }
        }
    }

    #[AsEventListener(UserDisabledEvent::class, priority: 100)]
    public function sendUserDisabledEmail(UserDisabledEvent $event): void
    {
        $user = $event->getUser();
        $htmlContent = $this->twig->render('emails/user/disabled.html.twig', [
            'user' => $user,
        ]);

        $textContent = $this->twig->render('emails/user/disabled.txt.twig', [
            'user' => $user,
        ]);

        $this->userService->sendEmailToUser(
            $user,
            $this->translator->trans(UserTranslationKeys::USER_EMAIL_ACCOUNT_DISABLED_SUBJECT),
            $htmlContent,
            $textContent
        );
    }
}

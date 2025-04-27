<?php

namespace App\EventListener;

use Twig\Environment;
use Symfony\Component\Mime\Email;
use App\Event\UserRegisteredEvent;
use App\Security\Authenticator\Passport\Credentials\IsActiveCreditnal;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class UserEventsListener{

    public function __construct(
        protected MailerInterface $mailer,
        protected Environment $twig,
        protected TranslatorInterface $translator,
        protected string $mailerFromAddress,
        protected string $appUrl,
    ){}

    #[AsEventListener( 'user.registered', priority: 100)]
    public function onUserRegistered( UserRegisteredEvent $event){
        $user = $event->getUser();
        
        // Render email templates
        $htmlContent = $this->twig->render('emails/registration.html.twig', [
            'user' => $user,
            'verificationToken' => $user->getVerificationToken(),
            'appUrl' => $this->appUrl,
        ]);
        
        $textContent = $this->twig->render('emails/registration.txt.twig', [
            'user' => $user,
            'verificationToken' => $user->getVerificationToken(),
            'appUrl' => $this->appUrl,
        ]);
        
        $email = new Email();
        $email->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.registration.subject'))
            ->text($textContent)
            ->html($htmlContent);

        $this->mailer->send($email);
    }

    #[AsEventListener( CheckPassportEvent::class, priority: 100)]
    public function checkPassport( CheckPassportEvent $event){
        $passport = $event->getPassport();
        if( $passport->hasBadge( IsActiveCreditnal::class)){
            $user = $passport->getUser();
            if (!$user->isActive()) {
                throw new LockedHttpException('user.account.inactive');
            }
        }
    }

}
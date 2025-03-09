<?php

namespace App\EventListener;

use App\Event\UserRegisteredEvent;
use Symfony\Component\Mailer\Mailer;

class UserEventsListener{

    public function __construct(
        protected Mailer $mailer,
    ){}

    public function onUserRegistered( UserRegisteredEvent $event){
        $user = $event->getUser();
        die( '1');
    }
}
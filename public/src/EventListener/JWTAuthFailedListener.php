<?php

namespace App\EventListener;

use App\Response\ApiResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;

final class JWTAuthFailedListener{

    #[AsEventListener( Events::AUTHENTICATION_FAILURE, priority: 100 )]
    public function onAuthFailed( AuthenticationFailureEvent $event ){
        $event->setResponse( new ApiResponse( 'user.login.failed', false, 401, "UNAUTHORIZED_HTTP_EXCEPTION"));
    }
}
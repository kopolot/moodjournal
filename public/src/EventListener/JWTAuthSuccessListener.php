<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

final class JWTAuthSuccessListener{

    #[AsEventListener( Events::AUTHENTICATION_SUCCESS, priority: 100 )]
    public function onAuthFailed( AuthenticationSuccessEvent $event ){
        $token = $event->getData()[ 'token'];
        $newData = [
            'success' => true,
            'message' => [ 'user.login.success'],
            'error' => '',
            'data' => [
                'jwt_token' => $token
            ]
        ];
        $event->setData( $newData);
    }
}
<?php

namespace App\EventListener;

use App\Repository\UserRepository;
use App\Response\ApiResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class UserAuthentiactionListener{

    public function __construct(
        private UserRepository $userRepository,
    ){}
    
    #[AsEventListener( Events::AUTHENTICATION_SUCCESS, priority: 100)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event){
        $user = $event->getUser();
        $user->setLastLogin(new \DateTime());
        $this->userRepository->save($user);
        $token = $event->getData()['token'];
        $newData = [
            'success' => true,
            'message' => ['user.login.success'],
            'error' => '',
            'data' => [
                'jwt_token' => $token
            ]
        ];
        $event->setData($newData);
    }

    #[AsEventListener( Events::AUTHENTICATION_FAILURE, priority: 100)]
    public function onAuthFailed(AuthenticationFailureEvent $event){
        throw new UnauthorizedHttpException(
            '',
            'user.login.failed',
        );
        // $event->setResponse( new ApiResponse(
        //         'user.login.failed',
        //         false,
        //         401,
        //         "UNAUTHORIZED_HTTP_EXCEPTION"
        //     )
        // );
    }

    #[AsEventListener( Events::JWT_NOT_FOUND, priority: 100)]
    #[AsEventListener( Events::JWT_INVALID, priority: 100)]
    public function onUserNotFound( AuthenticationFailureEvent $event ){
        throw new UnauthorizedHttpException(
            'Bearer',
            'user.session.not_found',
        );
        // $event->setResponse( new ApiResponse(
        //         'user.not.found',
        //         false,
        //         401,
        //         "UNAUTHORIZED_HTTP_EXCEPTION"
        //     )
        // );
    }

    #[AsEventListener( Events::JWT_EXPIRED, priority: 100)]
    public function onSessionExpired( JWTExpiredEvent $event ){
        throw new UnauthorizedHttpException(
            'Bearer',
            'user.session.expired',
        );
        // $event->setResponse( new ApiResponse(
        //         'user.session.expired',
        //         false,
        //         401,
        //         "SESSION_EXPIRED_HTTP_EXCEPTION"
        //     )
        // );
    }
}

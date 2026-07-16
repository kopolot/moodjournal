<?php

namespace App\EventListener;

use App\Repository\UserRepository;
use DateTime;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

final class UserAuthenticationListener{

    public function __construct(
        private UserRepository $userRepository,
        private RequestStack $requestStack,
    ){}
    
    /**
     * Change the last login date of the user and change response data
     * @param AuthenticationSuccessEvent $event
     * @return void
     */
    #[AsEventListener( Events::AUTHENTICATION_SUCCESS, priority: 100)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event){
        $user = $event->getUser();
        $user->setLastLogin(new \DateTime());
        $this->userRepository->save($user);
        $token = $event->getData()['token'];
        $newData = [
            'success' => true,
            'message' => [\App\Translation\UserTranslationKeys::USER_LOGIN_SUCCESS],
            'error' => '',
            'data' => [
                'jwt_token' => $token
            ]
        ];
        $event->setData($newData);
    }

    /**
     * Change the response data when authentication fails
     *
     * @param AuthenticationFailureEvent $event
     * @return void
     */
    #[AsEventListener( Events::AUTHENTICATION_FAILURE, priority: 100)]
    public function onAuthFailed(AuthenticationFailureEvent $event){
        throw new UnauthorizedHttpException(
            '',
            \App\Translation\UserTranslationKeys::USER_LOGIN_FAILED,
        );
    }

    /**
     * Change the response data when JWT is authenticated
     *
     * @param JWTAuthenticatedEvent $event
     * @return void
     */
    #[AsEventListener( Events::JWT_NOT_FOUND, priority: 100)]
    #[AsEventListener( Events::JWT_INVALID, priority: 100)]
    public function onUserNotFound( AuthenticationFailureEvent $event ){
        throw new UnauthorizedHttpException(
            'Bearer',
            \App\Translation\UserTranslationKeys::USER_SESSION_NOT_FOUND,
        );
    }

    /**
     * Change the response data when JWT is expired
     *
     * @param JWTExpiredEvent $event
     * @return void
     */
    #[AsEventListener( Events::JWT_EXPIRED, priority: 100)]
    public function onSessionExpired( JWTExpiredEvent $event ){
        throw new UnauthorizedHttpException(
            'Bearer',
            \App\Translation\UserTranslationKeys::USER_SESSION_NOT_FOUND,
        );
    }


    #[AsEventListener( Events::JWT_CREATED, priority: 100)]
    public function onJWTCreated( JWTCreatedEvent $event ){
        $request = $this->requestStack->getCurrentRequest();
        $bodyParams = $request->getPayload();
        $payload = $event->getData();
        if( $bodyParams->get( 'remember_me', false) )
            $payload['exp'] = (new DateTime( '2100-01-01'))->getTimestamp();
        $event->setData( $payload);
    }


    // email login events

    /**
     * Validate is verified. Change the response data on email login and set the last login date
     *
     * @param LoginSuccessEvent $event
     * @return void
     */
    #[AsEventListener( LoginSuccessEvent::class, priority: 100)]
    public function onLoginSuccess( LoginSuccessEvent $event ){
        $user = $event->getUser();
        if( !$user->isVerified())
            throw new LockedHttpException(
                'user.login.not_verified',
            );
        if( !$user->isActive())
            $user->setIsActive(true);
        $user->setLastLogin(new \DateTime());
        $this->userRepository->save($user);
    }
}

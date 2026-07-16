<?php

namespace App\Security\Authenticator;

use App\Security\Authenticator\Passport\Credentials\IsActiveCredential;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator as BaseJWTAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;

class JWTIsActiveAuthenticator extends BaseJWTAuthenticator
{

    private TokenExtractorInterface $tokenExtractor;
    private JWTTokenManagerInterface $jwtManager;
    private EventDispatcherInterface $eventDispatcher;
    private UserProviderInterface $userProvider;
    private ?TranslatorInterface $translator;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $eventDispatcher,
        TokenExtractorInterface $tokenExtractor,
        UserProviderInterface $userProvider,
        ?TranslatorInterface $translator = null
    ) {
        parent::__construct($jwtManager, $eventDispatcher, $tokenExtractor, $userProvider, $translator);
        $this->jwtManager = $jwtManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->tokenExtractor = $tokenExtractor;
        $this->userProvider = $userProvider;
        $this->translator = $translator;
    }

    /**
     * @inheritdoc
     */
    public function authenticate(Request $request): Passport
    {
        $token = $this->getTokenExtractor()->extract($request);
        if ($token === false) {
            throw new \LogicException('Unable to extract a JWT token from the request. Also, make sure to call `supports()` before `authenticate()` to get a proper client error.');
        }

        try {
            if (!$payload = $this->jwtManager->parse($token)) {
                throw new InvalidTokenException('Invalid JWT Token');
            }
        } catch (JWTDecodeFailureException $e) {
            if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
                throw new ExpiredTokenException();
            }

            throw new InvalidTokenException('Invalid JWT Token', 0, $e);
        }

        $idClaim = $this->jwtManager->getUserIdClaim();
        if (!isset($payload[$idClaim])) {
            throw new InvalidPayloadException($idClaim);
        }

        $passport = new Passport(
            new UserBadge(
                (string) $payload[$idClaim],
                fn ($userIdentifier) => $this->loadUser($payload, $userIdentifier)
            ),
            new IsActiveCredential
        );

        $passport->setAttribute('payload', $payload);
        $passport->setAttribute('token', $token);

        return $passport;
    }
}
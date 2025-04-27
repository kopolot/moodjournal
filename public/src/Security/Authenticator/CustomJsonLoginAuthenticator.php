<?php

namespace App\Security\Authenticator;

use App\Translation\UserTranslationKeys;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

#[AsAlias('security.authenticator.json_login.not_logged_in')]
class CustomJsonLoginAuthenticator extends JsonLoginAuthenticator
{
    private array $options;
    private PropertyAccessorInterface $propertyAccessor;
    private ?TranslatorInterface $translator = null;

    public function __construct(
        private HttpUtils $httpUtils,
        private UserProviderInterface $userProvider,
        private ?AuthenticationSuccessHandlerInterface $successHandler = null,
        private ?AuthenticationFailureHandlerInterface $failureHandler = null,
        array $options = [],
        ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        // Call the parent constructor
        parent::__construct($httpUtils, $userProvider, $successHandler, $failureHandler, $options, $propertyAccessor);
        $this->options = array_merge(['username_path' => 'username', 'password_path' => 'password'], $options);
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $data = json_decode($request->getContent());
            if (!$data instanceof \stdClass) {
                throw new BadRequestHttpException('Invalid JSON.');
            }

            $credentials = $this->getCredentials($data);
        } catch (BadRequestHttpException $e) {
            $request->setRequestFormat('json');

            throw $e;
        }

        $userBadge = new UserBadge($credentials['username'], $this->userProvider->loadUserByIdentifier(...));
        $passport = new Passport($userBadge, new PasswordCredentials($credentials['password']), [new RememberMeBadge((array) $data)]);

        if ($this->userProvider instanceof PasswordUpgraderInterface) {
            $passport->addBadge(new PasswordUpgradeBadge($credentials['password'], $this->userProvider));
        }

        return $passport;
    }


    private function getCredentials(\stdClass $data): array
    {
        $credentials = [];
        try {
            $credentials['username'] = $this->propertyAccessor->getValue($data, $this->options['username_path']);

            if (!\is_string($credentials['username']) || '' === $credentials['username']) {
                throw new BadRequestHttpException( UserTranslationKeys::USER_LOGIN_FAILED);
            }
        } catch (AccessException $e) {
            throw new BadRequestHttpException( UserTranslationKeys::USER_LOGIN_FAILED, $e);
        }

        try {
            $credentials['password'] = $this->propertyAccessor->getValue($data, $this->options['password_path']);
            $this->propertyAccessor->setValue($data, $this->options['password_path'], null);

            if (!\is_string($credentials['password']) || '' === $credentials['password']) {
                throw new BadRequestHttpException( UserTranslationKeys::USER_LOGIN_FAILED);
            }
        } catch (AccessException $e) {
            throw new BadRequestHttpException( UserTranslationKeys::USER_LOGIN_FAILED, $e);
        }

        return $credentials;
    }
}
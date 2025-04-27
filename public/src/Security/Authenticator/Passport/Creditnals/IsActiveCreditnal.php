<?php

namespace App\Security\Authenticator\Passport\Credentials;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;

/**
 * Custom credentials checker that checks if the user is active.
 * @author kopolot
 */
class IsActiveCreditnal implements CredentialsInterface{
    
    public function isResolved(): bool
    {
        return true;
    }
}
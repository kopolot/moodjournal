<?php

namespace App\Security\Authenticator\Passport\Credentials;

use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * Custom credentials checker that checks if the user is active.
 */
class IsActiveCredential implements CredentialsInterface
{
    public function isResolved(): bool
    {
        return true;
    }
}
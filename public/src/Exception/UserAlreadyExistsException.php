<?php

namespace App\Exception;

use Symfony\Component\Translation\TranslatableMessage;

class UserAlreadyExistsException extends \RuntimeException{
    
    public function __construct()
    {
        parent::__construct(new TranslatableMessage( 'user.registration.already_exists', [], 'validators'), 409);
    }
}
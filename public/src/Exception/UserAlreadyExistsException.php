<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserAlreadyExistsException extends ConflictHttpException{

    public function __construct( string $message = 'user.registration.already_exists'){
        parent::__construct( $message);
    }
}
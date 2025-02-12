<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AsEventListener(  event: 'kernel.exception', priority: 100)]
class ValidationFailedExceptionListener{

    public function __invoke( ExceptionEvent $event){
        
        $exception = $event->getThrowable();

        if( 
            ( $exception instanceof UnprocessableEntityHttpException && $exception->getPrevious() instanceof ValidationFailedException && $exception = $exception->getPrevious() ) || 
            $exception instanceof ValidationFailedException ){

            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            $response = new JsonResponse([
                'success' => false,
                'status' => 'validation_errors',
                'messages' => $errors
            ], 400);

            $event->setResponse($response);
        }
    }
}
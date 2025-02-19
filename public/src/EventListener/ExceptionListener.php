<?php

namespace App\EventListener;

use App\Response\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Jawira\CaseConverter\Convert;
use Traversable;

final class ExceptionListener
{
    public function __construct(
        protected LoggerInterface $logger,
        protected string $environment,
    )
    {}

    public function onKernelException(ExceptionEvent $event) : void{
        $exception = $event->getThrowable();
        //  format   object.prop.validator.violation
        if ($exception instanceof HttpExceptionInterface) {
            // tu trzeba to oogarnac
            $response = $this->getHttpExceptionResponse( $exception);
            $event->setResponse($response);
            return;
        }

        if( 'prod' === $this->environment){
            $this->logger->critical( $exception);
            $event->setResponse( $this->getServerErrorResponse());
        }
    }

    public function getHttpExceptionResponse( HttpExceptionInterface $exception): ApiResponse{
        $exceptionName = (new \ReflectionClass( $exception))->getShortName();
        
        if( is_object( $exception->getPrevious()) && $exception->getPrevious() instanceof \Symfony\Component\Validator\Exception\ValidationFailedException){
            $violations = $exception?->getPrevious()->getViolations();
            $message = $this->getAssertionFailedMessages( $violations);
        }else
            // tu trzeba ogarnac mesages
            $message = $exception->getMessage();

        $response = new ApiResponse(
            $message,
            false,
            $exception->getStatusCode(),
            (new Convert( $exceptionName))->toMacro(),
        );
        return $response;
    }

    protected function getServerErrorResponse(): ApiResponse{
        return new ApiResponse(
            'server.internal_error',
            false,
            500,
            'INTERNAL_SERVER_ERROR'
        );
    }

    private function getAssertionFailedMessages( Traversable $violations): array{
        $message = [];
        foreach( $violations as $violation){
            /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
            $parts = [
                strtolower(
                    ( new \ReflectionClass( $violation->getRoot()) )->getShortName()
                ),
                strtolower( $violation->getPropertyPath()),
                strtolower(
                    ( new \ReflectionClass( $violation->getConstraint()) )->getShortName()
                ),
                strtolower( $violation->getConstraint()->getErrorName( $violation->getCode()))
            ];
            $message[] = implode( '.', $parts);
        }
        return $message;
    }
}

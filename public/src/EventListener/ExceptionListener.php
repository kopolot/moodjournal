<?php

namespace App\EventListener;

use App\Response\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Jawira\CaseConverter\Convert;

class ExceptionListener
{
    public function __construct(
        protected LoggerInterface $logger
    )
    {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $exceptionName = (new \ReflectionClass( $exception))->getShortName();
            $response = new ApiResponse(
                $exception->getMessage(),
                false,
                $exception->getStatusCode(),
                (new Convert( $exceptionName))->toMacro(),
            );

            $event->setResponse($response);
            return;
        }

        $this->logger->error( $exception);
        
        $response = new ApiResponse(
            'Please contact administrator.',
            false,
            500,
            'INTERNAL_SERVER_ERROR'
        );

        $event->setResponse($response);
    }
}

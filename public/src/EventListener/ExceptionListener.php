<?php

namespace App\EventListener;

use App\Exception\AbstractApiException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AbstractApiException) {
            $event->setResponse(
                new JsonResponse(
                    $exception->getResponseContent(), 
                    $exception->getHttpCode(),
                    [],
                )
            );
            return;
        }

        // // 🔥 Jeśli to HttpException (np. 404, 403), pobierz status
        // if ($exception instanceof HttpExceptionInterface) {
        //     $response = new JsonResponse([
        //         'success' => false,
        //         'error' => strtoupper(str_replace(' ', '_', $exception->getMessage())),
        //         'message' => $exception->getMessage(),
        //         'data' => null
        //     ], $exception->getStatusCode());

        //     $event->setResponse($response);
        //     return;
        // }

        $internal_error = new \App\Exception\InternalServerError( null, $exception);
        $response = new JsonResponse(
            $internal_error->getResponseContent(),
            $internal_error->getHttpCode(),
            [],
        );

        $event->setResponse($response);
    }
}

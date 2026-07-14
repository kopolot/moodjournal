<?php

namespace App\EventListener;

use Traversable;
use Psr\Log\LoggerInterface;
use App\Response\ApiResponse;
use Jawira\CaseConverter\Convert;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener(
    event: KernelEvents::EXCEPTION,
    method: 'onKernelException',
    priority: 256
)]
final class ExceptionListener
{
    public function __construct(
        protected LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        protected string $environment,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof HttpExceptionInterface) {
            $response = $this->getHttpExceptionResponse($exception);
            $event->setResponse($response);
        }

        if ('prod' === $this->environment) {
            $this->logger->critical($exception);
            $event->setResponse($this->getServerErrorResponse());
        }
    }

    public function getHttpExceptionResponse(HttpExceptionInterface $exception): ApiResponse
    {
        $exceptionName = (new \ReflectionClass($exception))->getShortName();
        $message = explode("\n", $exception->getMessage());

        if ($this->environment === 'dev') {
            $this->logger->error($exception);
            return new ApiResponse(
                $message,
                false,
                $exception->getStatusCode(),
                (new Convert($exceptionName))->toMacro(),
                [
                    'exception' => $exceptionName,
                    'message' => $message,
                    'trace' => $exception->getTraceAsString(),
                ]
            );
        }
        $response = new ApiResponse(
            $message,
            false,
            $exception->getStatusCode(),
            (new Convert($exceptionName))->toMacro(),
        );
        return $response;
    }

    protected function getServerErrorResponse(): ApiResponse
    {
        return new ApiResponse(
            \App\Translation\ServerTranslationKeys::SERVER_INTERNAL_SERVER_ERROR,
            false,
            500,
            'INTERNAL_SERVER_ERROR'
        );
    }

    private function getAssertionFailedMessages(Traversable $violations): array
    {
        $message = [];
        foreach ($violations as $violation) {
            /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
            $parts = [
                strtolower(
                    (new \ReflectionClass($violation->getRoot()))->getShortName()
                ),
                strtolower($violation->getPropertyPath()),
                strtolower(
                    (new \ReflectionClass($violation->getConstraint()))->getShortName()
                ),
                strtolower($violation->getConstraint()->getErrorName($violation->getCode()))
            ];
            $message[] = implode('.', $parts);
        }
        return $message;
    }
}

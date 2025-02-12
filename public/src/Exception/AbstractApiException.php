<?php

namespace App\Exception;

abstract class AbstractApiException extends \RuntimeException{

    public function __construct( 
        ?string $message = null,
        ?\Throwable $previous = null,
    ){
        parent::__construct( $message ?? static::DEFAULT_MESSAGE, 0, $previous);
    }

    final public function getResponseContent( ): array{
        return [
            'success' => false,
            'error' => $this->getErrorCode(),
            'message' => $this->getMessage(),
            "data" => null,
        ];
    }

    protected function getErrorCode(): string{
        return static::ERROR_CODE;
    }

    public function getHttpCode(): int{
        return static::HTTP_CODE;
    }
}
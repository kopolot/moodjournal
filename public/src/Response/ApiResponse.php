<?php

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse{

    public function __construct(
        string $message,
        bool $success = true,
        int $statusCode = 200,
        string $error = '',
        array $data = [],
    )
    {
        parent::__construct( 
            [
                'success' => $success,
                'error' => $error,
                'message' => $message,
                'data' => $data
            ],
            $statusCode
        );
    }
}
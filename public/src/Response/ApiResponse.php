<?php

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse{

    /**
     *
     * @param array<string>|string $message
     * @param bool $success
     * @param int $statusCode
     * @param string $error
     * @param array $data
     */
    public function __construct(
        array|string|object $message,
        bool $success = true,
        int $statusCode = 200,
        string $error = '',
        array $data = [],
    )
    {
        $message = is_string( $message) ? [ $message] : $message;
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
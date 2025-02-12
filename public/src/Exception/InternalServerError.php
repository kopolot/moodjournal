<?php

namespace App\Exception;

class InternalServerError extends AbstractApiException{

    final protected const ERROR_CODE = "INTERNAL_SERVER_ERROR";
    final protected const HTTP_CODE = 500;
    protected const DEFAULT_MESSAGE = "Please contact administrator.";
}
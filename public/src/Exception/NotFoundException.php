<?php

namespace App\Exception;

class NotFoundException extends AbstractApiException{

    final protected const ERROR_CODE = "NOT_FOUND";
    final protected const HTTP_CODE = 404;
    protected const DEFAULT_MESSAGE = "Gateway not found.";
}
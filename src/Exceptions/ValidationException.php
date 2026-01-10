<?php

namespace GestorSimples\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(array $errors = [], string $message = "Validation failed", int $code = 422, \Throwable $previous = null)
    {
        parent::__construct($message, $errors, $code, $previous);
    }
}
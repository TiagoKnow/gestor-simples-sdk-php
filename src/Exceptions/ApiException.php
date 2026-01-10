<?php

namespace GestorSimples\Exceptions;

class ApiException extends \Exception
{
    protected array $errors = [];

    public function __construct(string $message = "", array $errors = [], int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
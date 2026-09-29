<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /** @var array<string, string> */
    private array $errors;

    /**
     * @param array<string, string|array<int, string>> $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, 422);
        $normalized = [];
        foreach ($errors as $field => $error) {
            $normalized[(string)$field] = is_array($error) ? (string)reset($error) : (string)$error;
        }
        $this->errors = $normalized;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Exception;

class ValidationException extends \RuntimeException
{
    private array $errors;

    public function __construct(array $errors, int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct(implode('; ', $errors), $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

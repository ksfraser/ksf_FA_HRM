<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Exception;

class EmployeeNotFoundException extends \RuntimeException
{
    public function __construct($identifier, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Employee not found: {$identifier}", $code, $previous);
    }
}

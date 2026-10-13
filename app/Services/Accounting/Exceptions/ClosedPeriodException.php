<?php

namespace App\Services\Accounting\Exceptions;

class ClosedPeriodException extends AccountingException
{
    public function __construct(string $message = 'The date falls inside a closed fiscal period.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

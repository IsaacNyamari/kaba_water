<?php

namespace App\Services\Accounting\Exceptions;

class InvalidAccountException extends AccountingException
{
    public function __construct(string $message = 'Invalid account for this entry.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

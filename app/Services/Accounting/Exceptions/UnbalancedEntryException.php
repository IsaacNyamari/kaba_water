<?php

namespace App\Services\Accounting\Exceptions;

class UnbalancedEntryException extends AccountingException
{
    public function __construct(string $message = 'The journal entry does not balance.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

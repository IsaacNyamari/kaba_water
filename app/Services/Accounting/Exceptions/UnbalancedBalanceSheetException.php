<?php

namespace App\Services\Accounting\Exceptions;

class UnbalancedBalanceSheetException extends AccountingException
{
    public function __construct(string $message = 'The balance sheet does not balance.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

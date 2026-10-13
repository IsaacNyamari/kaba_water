<?php

namespace App\Enums;

enum ExpensePaymentMethod: string
{
    case Cash   = 'cash';
    case Mpesa  = 'mpesa';
    case Bank   = 'bank';
    case Cheque = 'cheque';
    case Other  = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash   => 'Cash',
            self::Mpesa  => 'M-Pesa',
            self::Bank   => 'Bank Transfer',
            self::Cheque => 'Cheque',
            self::Other  => 'Other',
        };
    }
}

<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft   = 'draft';
    case Sent    = 'sent';
    case Paid    = 'paid';
    case Overdue = 'overdue';
    case Void    = 'void';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft   => 'zinc',
            self::Sent    => 'blue',
            self::Paid    => 'emerald',
            self::Overdue => 'red',
            self::Void    => 'zinc',
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::Sent, self::Overdue], true);
    }
}

<?php

namespace App\Enums;

enum NormalSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

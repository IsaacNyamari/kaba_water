<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash   = 'cash';
    case Mpesa  = 'mpesa';
    case Bank   = 'bank';
    case Stripe = 'stripe';
    case Other  = 'other';

    public function label(): string
    {
        return match($this) {
            self::Cash   => 'Cash',
            self::Mpesa  => 'M-Pesa',
            self::Bank   => 'Bank Transfer',
            self::Stripe => 'Card (Stripe)',
            self::Other  => 'Other',
        };
    }
}
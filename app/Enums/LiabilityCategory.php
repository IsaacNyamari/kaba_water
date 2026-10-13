<?php

namespace App\Enums;

enum LiabilityCategory: string
{
    case Loan           = 'loan';
    case SupplierCredit = 'supplier_credit';
    case TaxPayable     = 'tax_payable';
    case Other          = 'other';

    public function label(): string
    {
        return match($this) {
            self::Loan           => 'Bank Loan',
            self::SupplierCredit => 'Supplier Credit',
            self::TaxPayable     => 'Tax Payable',
            self::Other          => 'Other',
        };
    }
}
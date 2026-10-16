<?php

namespace App\Observers;

use App\Models\Tariff;
use App\Models\Vendor;
use App\Services\Accounting\ChartOfAccountsService;

/** Every new vendor gets the standard chart of accounts and the default KES 200 / m³ tariff. */
class VendorObserver
{
    public function __construct(protected ChartOfAccountsService $chart) {}

    public function created(Vendor $vendor): void
    {
        $this->chart->seedDefaultsFor($vendor);
        Tariff::ensureDefaultFor($vendor->id);
    }
}

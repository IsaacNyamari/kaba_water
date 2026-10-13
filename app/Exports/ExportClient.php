<?php

namespace App\Exports;

use App\Models\Client;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportClient implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return Client::all([
            'vendor_id',
            'account_no',
            'name',
            'phone',
            'email',
            'status',
            'created_at',
        ]);
    }

    public function headings(): array
    {
        return [
            'Vendor ID',
            'Account No',
            'Name',
            'Phone',
            'Email',
            'Status',
            'Created At',
        ];
    }
}

<?php

namespace App\Imports;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Str;

class ClientsImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading
{
    protected int $vendorId;
    protected ?int $nextSeq = null;

    public function __construct(?int $vendorId = null)
    {
        $this->vendorId = $vendorId ?? auth()->user()->vendor_id;
    }

    public function model(array $row): ?Model
    {
        if (blank($row['name'] ?? null) && blank($row['phone'] ?? null)) {
            return null;
        }

        $name  = trim((string) ($row['name'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));

        // De-duplicate on phone only when a phone was given — otherwise every
        // phone-less row after the first would be silently dropped.
        if ($phone !== '') {
            $exists = Client::withoutGlobalScope('vendor')
                ->where('vendor_id', $this->vendorId)
                ->where('phone', $phone)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        return new Client([
            'vendor_id'  => $this->vendorId,
            'account_no' => $this->reserveAccountNo(),
            'name'       => $name,
            'phone'      => $phone !== '' ? $phone : null,
            'email'      => filled($row['email'] ?? null) ? trim((string) $row['email']) : null,
            'status'     => $this->normalizeStatus($row['status'] ?? null),
        ]);
    }

    protected function reserveAccountNo(): string
    {
        if ($this->nextSeq === null) {
            $last = Client::withoutGlobalScope('vendor')
                ->where('vendor_id', $this->vendorId)
                ->orderByDesc('id')
                ->value('account_no');

            $this->nextSeq = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
        }

        return 'C' . str_pad((string) $this->nextSeq++, 5, '0', STR_PAD_LEFT);
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'phone'  => ['nullable', 'max:32'],
            'email'  => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string'],
        ];
    }

    protected function normalizeStatus(?string $value): string
    {
        return match (Str::lower(trim((string) $value))) {
            '0', 'inactive', 'disabled', 'no' => 'inactive',
            default                            => 'active', // blank or anything else
        };
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

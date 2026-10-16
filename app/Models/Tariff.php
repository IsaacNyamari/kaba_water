<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use BelongsToVendor;

    protected $fillable = ['vendor_id', 'name', 'type', 'rate', 'tiers', 'is_active'];

    protected $casts = ['tiers' => 'array', 'is_active' => 'boolean'];

    /** Name and price (KES per m³) of the tariff every workspace starts with. */
    public const DEFAULT_NAME = 'Standard rate';

    public const DEFAULT_RATE = '200.00';

    /**
     * The vendor's active tariff; when it has none, the default flat
     * KES 200 / m³ tariff is created so readings can always be billed.
     * The vendor can change or replace it under Tariffs.
     */
    public static function ensureDefaultFor(int $vendorId): self
    {
        return static::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->oldest('id')
            ->first()
            ?? static::withoutGlobalScope('vendor')->create([
                'vendor_id' => $vendorId,
                'name' => self::DEFAULT_NAME,
                'type' => 'flat',
                'rate' => self::DEFAULT_RATE,
                'is_active' => true,
            ]);
    }

    public function chargeFor(float $consumption): float
    {
        if ($this->type === 'flat') {
            return round($consumption * (float) $this->rate, 2);
        }

        $remaining = $consumption;
        $last = 0;
        $total = 0.0;

        foreach (($this->tiers ?? []) as $tier) {
            $upTo = $tier['up_to'] ?? null;
            $band = $upTo === null ? $remaining : max(0, min($upTo, $consumption) - $last);
            $total += $band * (float) $tier['rate'];
            $last = $upTo ?? $last;
            $remaining -= $band;
            if ($remaining <= 0) {
                break;
            }
        }

        return round($total, 2);
    }
}

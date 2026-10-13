<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use BelongsToVendor;

    protected $fillable = ['vendor_id', 'name', 'type', 'rate', 'tiers', 'is_active'];
    protected $casts    = ['tiers' => 'array', 'is_active' => 'boolean'];

    public function chargeFor(float $consumption): float
    {
        if ($this->type === 'flat') {
            return round($consumption * (float) $this->rate, 2);
        }

        $remaining = $consumption;
        $last      = 0;
        $total     = 0.0;

        foreach (($this->tiers ?? []) as $tier) {
            $upTo = $tier['up_to'] ?? null;
            $band = $upTo === null ? $remaining : max(0, min($upTo, $consumption) - $last);
            $total += $band * (float) $tier['rate'];
            $last = $upTo ?? $last;
            $remaining -= $band;
            if ($remaining <= 0) break;
        }

        return round($total, 2);
    }
}
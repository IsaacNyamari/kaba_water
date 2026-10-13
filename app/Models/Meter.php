<?php

namespace App\Models;

use App\Enums\MeterStatus;
use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meter extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'client_id', 'serial',
        'location', 'installed_at', 'status',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'status'       => MeterStatus::class,
    ];

    public function client(): BelongsTo  { return $this->belongsTo(Client::class); }
    public function readings(): HasMany  { return $this->hasMany(Reading::class)->latest('read_at'); }

    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->latestOfMany('read_at');
    }
}
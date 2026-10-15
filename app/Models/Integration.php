<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A vendor's connection to an outside service: M-Pesa (Daraja), SMS,
 * prepaid token vending or smart-meter data. Credentials are stored in the
 * encrypted config and never serialised.
 */
class Integration extends Model
{
    use BelongsToVendor;

    public const MPESA = 'mpesa';

    public const SMS = 'sms';

    public const TOKEN_VENDING = 'token_vending';

    public const SMART_METERS = 'smart_meters';

    protected $fillable = ['vendor_id', 'type', 'is_enabled', 'config', 'webhook_token', 'last_used_at'];

    protected $hidden = ['config', 'webhook_token'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'encrypted:array',
        'last_used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Integration $integration) {
            $integration->webhook_token ??= Str::random(48);
        });
    }

    /** The vendor's integration of a type, created (disabled) on first use. */
    public static function for(int $vendorId, string $type): self
    {
        return static::withoutGlobalScope('vendor')->firstOrCreate(
            ['vendor_id' => $vendorId, 'type' => $type],
            ['is_enabled' => false, 'config' => []],
        );
    }

    /** The enabled integration of a type, or null. */
    public static function active(int $vendorId, string $type): ?self
    {
        return static::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where('type', $type)
            ->where('is_enabled', true)
            ->first();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }

    public function rotateWebhookToken(): void
    {
        $this->update(['webhook_token' => Str::random(48)]);
    }
}

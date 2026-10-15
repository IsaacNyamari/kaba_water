<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A prepaid water token sold for a meter: what was paid, what went to arrears and the token itself. */
class PrepaidToken extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'meter_id', 'client_id', 'mpesa_transaction_id', 'amount_paid', 'arrears_deducted',
        'token_amount', 'units', 'token', 'provider', 'provider_reference', 'status', 'error',
        'sms_sent_at', 'issued_by',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'arrears_deducted' => 'decimal:2',
        'token_amount' => 'decimal:2',
        'units' => 'decimal:3',
        'sms_sent_at' => 'datetime',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(MpesaTransaction::class, 'mpesa_transaction_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** The token in groups of four, the way keypads print them. */
    public function formattedToken(): string
    {
        return trim(chunk_split((string) $this->token, 4, ' '));
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log of every SMS the system sent (or tried to send) for a vendor. */
class SmsMessage extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'vendor_id', 'client_id', 'to', 'message', 'purpose', 'provider', 'status', 'provider_message_id', 'error',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

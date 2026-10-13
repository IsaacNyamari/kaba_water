<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use App\Enums\EmailCheckResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A request from someone outside the platform to get an account.
 * Platform-level (no vendor global scope) — reviewed by super admins.
 */
class AccessRequest extends Model
{
    public const TYPE_VENDOR = 'vendor';

    public const TYPE_CLIENT = 'client';

    protected $fillable = [
        'name', 'email', 'phone', 'organization', 'requested_type',
        'provider_name', 'account_reference', 'message', 'ip_address',
        'email_check', 'email_check_detail',
        'status', 'role', 'vendor_id', 'client_id',
        'reviewed_by', 'reviewed_at', 'auto_approved', 'rejection_reason',
        'invite_token_hash', 'invite_sent_at', 'invite_expires_at',
        'accepted_at', 'user_id',
    ];

    protected $hidden = ['invite_token_hash'];

    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'email_check' => EmailCheckResult::class,
            'auto_approved' => 'boolean',
            'reviewed_at' => 'datetime',
            'invite_sent_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withoutGlobalScope('vendor');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', AccessRequestStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === AccessRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === AccessRequestStatus::Approved;
    }

    public function inviteIsUsable(): bool
    {
        return $this->isApproved()
            && $this->invite_token_hash
            && $this->invite_expires_at?->isFuture();
    }

    /** Constant-time check of a plain token from the signup link. */
    public function tokenMatches(?string $plain): bool
    {
        return $plain !== null
            && $this->invite_token_hash !== null
            && hash_equals($this->invite_token_hash, hash('sha256', $plain));
    }

    /**
     * Issue a fresh single-use token (invalidating any earlier link).
     * Returns the PLAIN token — only its hash is stored.
     */
    public function issueInviteToken(): string
    {
        $plain = Str::random(64);

        $this->forceFill([
            'invite_token_hash' => hash('sha256', $plain),
            'invite_sent_at' => now(),
            'invite_expires_at' => now()->addDays(config('access.invite_expires_days', 7)),
        ])->save();

        return $plain;
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}

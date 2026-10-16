<?php

namespace App\Models;

use App\Enums\VendorStatus;
use App\Models\Concerns\HasAccounting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasAccounting, HasFactory;

    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'logo_path', 'icon_path',
        'currency', 'status', 'plan_id', 'trial_ends_at', 'gemini_api_key', 'gemini_models',
    ];

    /** Never serialise the vendor's own API key (e.g. into Livewire state or JSON). */
    protected $hidden = ['gemini_api_key'];

    protected $casts = [
        'status' => VendorStatus::class,
        'trial_ends_at' => 'datetime',
        'gemini_api_key' => 'encrypted',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(Liability::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === VendorStatus::Active
            || ($this->status === VendorStatus::Trial && $this->trial_ends_at?->isFuture());
    }
}

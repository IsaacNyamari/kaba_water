<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use App\Models\Expense;
use App\Models\Liability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToVendor;

    protected $fillable = ['vendor_id', 'name', 'email', 'phone', 'address', 'notes', 'status'];

    public function expenses(): HasMany    { return $this->hasMany(Expense::class); }
    public function liabilities(): HasMany { return $this->hasMany(Liability::class); }
}
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToVendor;

    protected $fillable = ['vendor_id', 'name', 'slug', 'color', 'is_system'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
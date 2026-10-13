<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'monthly_price', 'max_clients', 'max_meters', 'features'];
    protected $casts    = ['features' => 'array', 'monthly_price' => 'decimal:2'];

    public function vendors(): HasMany { return $this->hasMany(Vendor::class); }
}
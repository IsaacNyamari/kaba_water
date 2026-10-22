<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A building, estate or plot owned by a property-owner workspace; holds its units. */
class Property extends Model
{
    use BelongsToVendor;

    protected $fillable = ['vendor_id', 'name', 'location', 'notes'];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('house_number');
    }
}

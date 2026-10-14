<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A material line on a project (casing pipe, gravel, cement…). Its stock and
 * cost come from the movement ledger — see App\Services\Projects\MaterialLedger.
 */
class ProjectMaterial extends Model
{
    protected $fillable = ['project_id', 'name', 'sku', 'unit', 'reorder_level', 'storage_location'];

    protected $casts = ['reorder_level' => 'decimal:3'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProjectMaterialMovement::class)->orderBy('movement_date')->orderBy('id');
    }
}

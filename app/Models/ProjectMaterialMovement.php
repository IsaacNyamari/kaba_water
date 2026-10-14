<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMaterialMovement extends Model
{
    /** ordered = purchase order (committed), received = into site stock, the rest leave stock. */
    public const TYPES = [
        'ordered' => 'Ordered (PO)',
        'received' => 'Received',
        'consumed' => 'Consumed',
        'returned' => 'Returned / transferred out',
        'wasted' => 'Wasted / damaged',
    ];

    protected $fillable = [
        'project_material_id', 'type', 'quantity', 'unit_cost', 'supplier_id', 'reference',
        'movement_date', 'recorded_by', 'notes', 'attachment_path',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(ProjectMaterial::class, 'project_material_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

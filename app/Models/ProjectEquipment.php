<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rig, compressor, pump, truck… used on borehole projects — owned or hired
 * from a project supplier. Separate from Water Billing's accounting assets.
 */
class ProjectEquipment extends Model
{
    use BelongsToVendor;

    protected $table = 'project_equipment';

    public const STATUSES = ['available' => 'Available', 'maintenance' => 'In maintenance', 'retired' => 'Retired'];

    protected $fillable = [
        'vendor_id', 'name', 'identifier', 'type', 'ownership', 'project_supplier_id',
        'default_rate', 'rate_unit', 'status', 'notes',
    ];

    protected $casts = ['default_rate' => 'decimal:2'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ProjectSupplier::class, 'project_supplier_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectTeamAssignment::class, 'equipment_id');
    }

    public function label(): string
    {
        return $this->name.($this->identifier ? " ({$this->identifier})" : '');
    }
}

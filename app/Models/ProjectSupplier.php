<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier for borehole projects (drilling contractors, pipe and casing
 * suppliers, equipment hire…). Separate from Water Billing's suppliers.
 */
class ProjectSupplier extends Model
{
    use BelongsToVendor;

    public const CATEGORIES = [
        'drilling' => 'Drilling contractor',
        'materials' => 'Pipes, casing & materials',
        'pumps' => 'Pumps & solar',
        'equipment_hire' => 'Equipment hire',
        'transport' => 'Transport & fuel',
        'labour' => 'Labour & subcontractors',
        'testing' => 'Testing & surveys',
        'electrical' => 'Electrical & plumbing',
        'other' => 'Other',
    ];

    protected $fillable = [
        'vendor_id', 'name', 'category', 'contact_person', 'phone', 'email', 'address',
        'kra_pin', 'payment_terms', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class, 'supplier_id');
    }

    public function materialMovements(): HasMany
    {
        return $this->hasMany(ProjectMaterialMovement::class, 'supplier_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(ProjectEquipment::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? '—';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use App\Support\Money\MoneyMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Labour (a team member or casual worker) or equipment (one of the vendor's
 * assets, or hired) on a project. Cost = rate × units worked + extra cost.
 */
class ProjectTeamAssignment extends Model
{
    protected $fillable = [
        'project_id', 'kind', 'user_id', 'asset_id', 'name', 'role', 'start_date', 'end_date',
        'rate', 'rate_unit', 'units_worked', 'extra_cost', 'downtime_units', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rate' => 'decimal:2',
        'units_worked' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'downtime_units' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function cost(): string
    {
        return MoneyMath::add(MoneyMath::multiply((string) $this->rate, (string) $this->units_worked), (string) $this->extra_cost);
    }
}

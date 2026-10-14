<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    protected $fillable = [
        'project_id', 'name', 'sort_order', 'weight', 'is_mandatory', 'planned_date',
        'completed_at', 'completed_by', 'is_waived', 'waiver_reason',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'completed_at' => 'datetime',
        'is_mandatory' => 'boolean',
        'is_waived' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'milestone_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** Done or formally waived. */
    public function isSettled(): bool
    {
        return $this->completed_at !== null || $this->is_waived;
    }

    public function isOverdue(): bool
    {
        return ! $this->isSettled() && $this->planned_date && $this->planned_date->lt(today());
    }
}

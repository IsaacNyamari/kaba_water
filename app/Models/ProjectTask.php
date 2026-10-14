<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    public const STATUSES = ['todo' => 'To do', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'done' => 'Done'];

    protected $fillable = [
        'project_id', 'milestone_id', 'parent_id', 'depends_on_id', 'title', 'description',
        'assigned_to', 'priority', 'status', 'progress', 'start_date', 'due_date',
        'estimated_cost', 'blocker', 'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_date && $this->due_date->lt(today());
    }

    /** Waiting on a dependency that is not done yet. */
    public function isWaiting(): bool
    {
        return $this->dependsOn && $this->dependsOn->status !== 'done';
    }

    public function scopeOpen($query)
    {
        return $query->where('status', '!=', 'done');
    }
}

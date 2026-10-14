<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProgressReport extends Model
{
    protected $fillable = [
        'project_id', 'report_date', 'reported_by', 'stage', 'activities_completed', 'next_day_plan',
        'planned_depth_m', 'actual_depth_m', 'labour_summary', 'equipment_summary', 'weather',
        'challenges', 'incidents', 'risks', 'corrective_actions', 'progress_percent', 'attachments',
        'status', 'pm_remarks', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'approved_at' => 'datetime',
        'planned_depth_m' => 'decimal:2',
        'actual_depth_m' => 'decimal:2',
        'attachments' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

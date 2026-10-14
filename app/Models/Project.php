<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A borehole project — tracked internally from Day One to handover
 * against an approved cost budget. No client or revenue concepts.
 */
class Project extends Model
{
    use BelongsToVendor, SoftDeletes;

    protected $fillable = [
        'vendor_id', 'reference', 'name', 'description', 'status',
        'county', 'sub_county', 'latitude', 'longitude', 'site_details',
        'start_date', 'expected_completion_date', 'actual_completion_date', 'project_manager_id',
        'original_budget', 'current_budget', 'contingency', 'estimated_cost_to_complete', 'budget_alert_level',
        'planned_depth_m', 'actual_depth_m', 'diameter_mm', 'casing_requirements', 'pump_capacity',
        'scope', 'deliverables', 'special_requirements', 'technical', 'attachments',
        'closed_at', 'closed_by', 'final_statement',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'closed_at' => 'datetime',
        'original_budget' => 'decimal:2',
        'current_budget' => 'decimal:2',
        'contingency' => 'decimal:2',
        'estimated_cost_to_complete' => 'decimal:2',
        'planned_depth_m' => 'decimal:2',
        'actual_depth_m' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'technical' => 'array',
        'attachments' => 'array',
        'final_statement' => 'array',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProjectProgressReport::class)->latest('report_date');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class)->orderBy('name');
    }

    public function materialMovements(): HasManyThrough
    {
        return $this->hasManyThrough(ProjectMaterialMovement::class, ProjectMaterial::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectTeamAssignment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->latest('id');
    }

    /** Next per-vendor reference, BH-YYYY-NNN (based on the highest existing, not a count). */
    public static function nextReference(int $vendorId): string
    {
        $prefix = 'BH-'.now()->year.'-';

        $last = static::withoutGlobalScope('vendor')->withTrashed()
            ->where('vendor_id', $vendorId)
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function stageLabel(): string
    {
        return config("projects.stages.{$this->status}", ucfirst(str_replace('_', ' ', $this->status)));
    }

    public function stageColor(): string
    {
        return match ($this->status) {
            'draft' => 'zinc',
            'planning' => 'sky',
            'completed' => 'emerald',
            'on_hold' => 'amber',
            'cancelled' => 'rose',
            default => 'blue',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['draft', 'completed', 'cancelled'], true);
    }

    /** Record what happened on the project (who, what, when). */
    public function log(string $event, string $description, array $properties = []): ProjectActivity
    {
        return $this->activities()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['draft', 'completed', 'cancelled']);
    }
}

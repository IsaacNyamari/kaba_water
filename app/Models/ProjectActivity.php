<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only audit trail of everything that happens on a project. */
class ProjectActivity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'project_activity_log';

    protected $fillable = ['project_id', 'user_id', 'event', 'description', 'properties', 'created_at'];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

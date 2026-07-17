<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationPublicationSyncLog extends Model
{
    protected $fillable = [
        'pkpa_rotation_run_id',
        'old_published_assignment_id',
        'new_published_assignment_id',
        'change_type',
        'status',
        'impact_level',
        'message',
        'before_snapshot',
        'after_snapshot',
        'processed_by_core_user_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array', 'processed_at' => 'datetime'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function oldAssignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'old_published_assignment_id');
    }

    public function newAssignment(): BelongsTo
    {
        return $this->belongsTo(PkpaPublishedAssignment::class, 'new_published_assignment_id');
    }
}

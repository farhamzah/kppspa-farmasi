<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaLogbookEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_run_id',
        'entry_date',
        'period_start_date',
        'period_end_date',
        'title',
        'activity_summary',
        'learning_outcomes',
        'reflection',
        'problems_encountered',
        'follow_up_plan',
        'practice_minutes',
        'status',
        'submitted_at',
        'field_reviewed_at',
        'internal_reviewed_at',
        'locked_at',
        'submitted_by_core_user_id',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'row_version',
        'entry_key',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'practice_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'field_reviewed_at' => 'datetime',
            'internal_reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
            'row_version' => 'integer',
        ];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PkpaLogbookAttachment::class, 'pkpa_logbook_entry_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PkpaLogbookReview::class, 'pkpa_logbook_entry_id');
    }
}

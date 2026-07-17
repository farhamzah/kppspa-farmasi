<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationProgressSnapshot extends Model
{
    protected $fillable = [
        'pkpa_rotation_run_id',
        'snapshot_date',
        'scheduled_days_elapsed',
        'scheduled_days_total',
        'attendance_expected_count',
        'attendance_submitted_count',
        'attendance_approved_count',
        'attendance_problem_count',
        'logbook_expected_count',
        'logbook_submitted_count',
        'logbook_approved_count',
        'logbook_revision_count',
        'progress_percentage',
        'progress_status',
        'blocking_issues',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'scheduled_days_elapsed' => 'integer',
            'scheduled_days_total' => 'integer',
            'attendance_expected_count' => 'integer',
            'attendance_submitted_count' => 'integer',
            'attendance_approved_count' => 'integer',
            'attendance_problem_count' => 'integer',
            'logbook_expected_count' => 'integer',
            'logbook_submitted_count' => 'integer',
            'logbook_approved_count' => 'integer',
            'logbook_revision_count' => 'integer',
            'progress_percentage' => 'decimal:2',
            'blocking_issues' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }
}

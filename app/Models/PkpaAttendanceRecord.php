<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaAttendanceRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_run_id',
        'attendance_date',
        'attendance_type',
        'check_in_time',
        'check_out_time',
        'calculated_minutes',
        'status',
        'submission_status',
        'student_notes',
        'field_supervisor_notes',
        'source',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'submitted_by_core_user_id',
        'reviewed_by_core_user_id',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'row_version',
        'active_key',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'row_version' => 'integer',
        ];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(PkpaAttendanceCorrectionRequest::class, 'pkpa_attendance_record_id');
    }
}

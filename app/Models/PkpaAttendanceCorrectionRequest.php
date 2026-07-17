<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaAttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'pkpa_attendance_record_id',
        'request_type',
        'status',
        'reason',
        'before_snapshot',
        'proposed_snapshot',
        'requested_by_core_user_id',
        'reviewed_by_core_user_id',
        'approved_by_core_user_id',
        'rejected_by_core_user_id',
        'requested_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'proposed_snapshot' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(PkpaAttendanceRecord::class, 'pkpa_attendance_record_id');
    }
}

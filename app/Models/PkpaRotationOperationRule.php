<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaRotationOperationRule extends Model
{
    protected $fillable = [
        'pkpa_program_domain_id',
        'attendance_required',
        'require_check_in',
        'require_check_out',
        'allow_manual_attendance_time',
        'logbook_required',
        'logbook_frequency',
        'minimum_logbook_entries',
        'minimum_approved_attendance_days',
        'maximum_backdate_days',
        'submission_deadline_days',
        'field_supervisor_approval_required',
        'internal_supervisor_monitoring_enabled',
        'allow_student_edit_after_submit',
        'completion_requires_all_approved',
        'instructions',
        'is_active',
        'active_key',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'attendance_required' => 'boolean',
            'require_check_in' => 'boolean',
            'require_check_out' => 'boolean',
            'allow_manual_attendance_time' => 'boolean',
            'logbook_required' => 'boolean',
            'minimum_logbook_entries' => 'integer',
            'minimum_approved_attendance_days' => 'integer',
            'maximum_backdate_days' => 'integer',
            'submission_deadline_days' => 'integer',
            'field_supervisor_approval_required' => 'boolean',
            'internal_supervisor_monitoring_enabled' => 'boolean',
            'allow_student_edit_after_submit' => 'boolean',
            'completion_requires_all_approved' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }
}

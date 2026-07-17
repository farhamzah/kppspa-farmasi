<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationGuidanceSession extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_rotation_run_id', 'pkpa_rotation_report_id', 'report_version_id', 'guidance_type', 'guidance_date', 'supervisor_type', 'supervisor_core_user_id', 'topic', 'student_summary', 'supervisor_notes', 'follow_up_actions', 'status', 'student_acknowledged_at', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['guidance_date' => 'date', 'student_acknowledged_at' => 'datetime'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }
}

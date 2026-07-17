<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationSpecialTask extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_rotation_run_id', 'source_special_task_template_id', 'task_code_snapshot', 'task_title_snapshot', 'task_description_snapshot', 'instructions_snapshot', 'submission_type_snapshot', 'is_required_snapshot', 'field_supervisor_review_required_snapshot', 'internal_supervisor_review_required_snapshot', 'due_date', 'status', 'assigned_at', 'completed_at', 'cancelled_at', 'cancellation_reason', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['is_required_snapshot' => 'boolean', 'field_supervisor_review_required_snapshot' => 'boolean', 'internal_supervisor_review_required_snapshot' => 'boolean', 'due_date' => 'date', 'assigned_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PkpaSpecialTaskSubmission::class, 'pkpa_rotation_special_task_id');
    }
}

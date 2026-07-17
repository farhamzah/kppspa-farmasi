<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaSpecialTaskSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_rotation_special_task_id', 'version_number', 'title', 'description', 'original_filename', 'stored_filename', 'disk', 'path', 'mime_type', 'file_size', 'checksum', 'submission_notes', 'status', 'submitted_by_core_user_id', 'submitted_at'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size' => 'integer', 'submitted_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationSpecialTask::class, 'pkpa_rotation_special_task_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PkpaSpecialTaskReview::class, 'pkpa_special_task_submission_id');
    }
}

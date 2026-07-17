<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PkpaSpecialTaskReview extends Model
{
    protected $fillable = ['pkpa_special_task_submission_id', 'reviewer_type', 'reviewer_core_user_id', 'action', 'comments', 'reviewed_at', 'metadata'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PkpaSpecialTaskSubmission::class, 'pkpa_special_task_submission_id');
    }
}

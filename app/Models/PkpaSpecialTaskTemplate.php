<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaSpecialTaskTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_program_domain_id', 'code', 'title', 'description', 'instructions', 'submission_type', 'is_required', 'minimum_submissions', 'due_offset_days', 'allow_multiple_versions', 'field_supervisor_review_required', 'internal_supervisor_review_required', 'status', 'sort_order', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'allow_multiple_versions' => 'boolean', 'field_supervisor_review_required' => 'boolean', 'internal_supervisor_review_required' => 'boolean', 'minimum_submissions' => 'integer', 'due_offset_days' => 'integer', 'sort_order' => 'integer'];
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }
}

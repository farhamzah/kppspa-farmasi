<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationReportTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_program_domain_id', 'code', 'name', 'description', 'instructions', 'required_sections', 'allowed_file_types', 'maximum_file_size_kb', 'field_supervisor_confirmation_required', 'internal_supervisor_approval_required', 'submission_deadline_offset_days', 'status', 'is_current', 'current_key', 'created_by_core_user_id', 'updated_by_core_user_id'];

    protected function casts(): array
    {
        return ['required_sections' => 'array', 'allowed_file_types' => 'array', 'maximum_file_size_kb' => 'integer', 'field_supervisor_confirmation_required' => 'boolean', 'internal_supervisor_approval_required' => 'boolean', 'submission_deadline_offset_days' => 'integer', 'is_current' => 'boolean'];
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }
}

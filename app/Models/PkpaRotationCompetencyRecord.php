<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PkpaRotationCompetencyRecord extends Model
{
    protected $fillable = ['pkpa_rotation_run_id', 'source_competency_set_id', 'source_competency_item_id', 'competency_code_snapshot', 'competency_title_snapshot', 'competency_description_snapshot', 'achievement_criteria_snapshot', 'is_required_snapshot', 'evidence_required_snapshot', 'minimum_evidence_count_snapshot', 'status', 'student_notes', 'demonstrated_at', 'submitted_at', 'verified_at', 'revision_requested_at', 'verified_by_core_user_id', 'row_version'];

    protected function casts(): array
    {
        return ['is_required_snapshot' => 'boolean', 'evidence_required_snapshot' => 'boolean', 'minimum_evidence_count_snapshot' => 'integer', 'demonstrated_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'revision_requested_at' => 'datetime', 'row_version' => 'integer'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PkpaRotationCompetencyEvidence::class, 'pkpa_rotation_competency_record_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PkpaRotationCompetencyReview::class, 'pkpa_rotation_competency_record_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationCompetencyEvidence extends Model
{
    use SoftDeletes;

    protected $table = 'pkpa_rotation_competency_evidences';

    protected $fillable = ['pkpa_rotation_competency_record_id', 'evidence_type', 'title', 'description', 'logbook_entry_id', 'attendance_record_id', 'external_reference_url', 'original_filename', 'stored_filename', 'disk', 'path', 'mime_type', 'file_size', 'checksum', 'status', 'uploaded_by_core_user_id'];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function competencyRecord(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationCompetencyRecord::class, 'pkpa_rotation_competency_record_id');
    }
}

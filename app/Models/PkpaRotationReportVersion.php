<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationReportVersion extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_rotation_report_id', 'version_number', 'original_filename', 'stored_filename', 'disk', 'path', 'mime_type', 'file_size', 'checksum', 'change_summary', 'submission_notes', 'status', 'uploaded_by_core_user_id', 'submitted_at'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size' => 'integer', 'submitted_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationReport::class, 'pkpa_rotation_report_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationReport extends Model
{
    use SoftDeletes;

    protected $fillable = ['pkpa_rotation_run_id', 'source_report_template_id', 'report_code', 'title', 'status', 'current_version_id', 'submitted_at', 'field_confirmed_at', 'internal_approved_at', 'revision_requested_at', 'locked_at', 'field_confirmed_by_core_user_id', 'internal_approved_by_core_user_id', 'created_by_core_user_id', 'updated_by_core_user_id', 'row_version'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'field_confirmed_at' => 'datetime', 'internal_approved_at' => 'datetime', 'revision_requested_at' => 'datetime', 'locked_at' => 'datetime', 'row_version' => 'integer'];
    }

    public function rotationRun(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PkpaRotationReportVersion::class, 'pkpa_rotation_report_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationReportVersion::class, 'current_version_id');
    }
}

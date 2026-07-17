<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaGradeChangeRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pkpa_rotation_assessment_id', 'request_number', 'request_type', 'status',
        'reason', 'impact_summary', 'requested_by_core_user_id', 'reviewed_by_core_user_id',
        'approved_by_core_user_id', 'rejected_by_core_user_id', 'requested_at', 'reviewed_at',
        'approved_at', 'rejected_at', 'rejection_reason', 'applied_at',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PkpaRotationAssessment::class, 'pkpa_rotation_assessment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PkpaGradeChangeRequestItem::class, 'pkpa_grade_change_request_id');
    }
}

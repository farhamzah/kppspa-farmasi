<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaRotationPortfolio extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft', 'in_progress', 'submitted_to_field_supervisor', 'field_revision_requested',
        'field_verified', 'submitted_to_internal_supervisor', 'internal_revision_requested',
        'approved', 'locked', 'published', 'superseded', 'cancelled',
    ];

    protected $fillable = [
        'pkpa_rotation_run_id', 'pkpa_portfolio_template_id', 'pkpa_enrollment_id',
        'pkpa_program_id', 'practice_domain_id', 'portfolio_number', 'status',
        'is_current', 'current_key', 'identity_snapshot', 'placement_snapshot',
        'progress_snapshot', 'integrity_pact_version', 'integrity_pact_text',
        'integrity_acknowledged_at', 'integrity_acknowledged_by_core_user_id',
        'submitted_at', 'submitted_by_core_user_id', 'field_verified_at',
        'field_verified_by_core_user_id', 'internal_approved_at',
        'internal_approved_by_core_user_id', 'locked_at', 'locked_by_core_user_id',
        'published_at', 'published_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'portfolio_number' => 'integer',
            'is_current' => 'boolean',
            'identity_snapshot' => 'array',
            'placement_snapshot' => 'array',
            'progress_snapshot' => 'array',
            'integrity_acknowledged_at' => 'datetime',
            'submitted_at' => 'datetime',
            'field_verified_at' => 'datetime',
            'internal_approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function rotationRun(): BelongsTo { return $this->belongsTo(PkpaRotationRun::class, 'pkpa_rotation_run_id'); }
    public function template(): BelongsTo { return $this->belongsTo(PkpaPortfolioTemplate::class, 'pkpa_portfolio_template_id'); }
    public function enrollment(): BelongsTo { return $this->belongsTo(PkpaEnrollment::class, 'pkpa_enrollment_id'); }
    public function program(): BelongsTo { return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id'); }
    public function practiceDomain(): BelongsTo { return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id'); }
    public function sectionRecords(): HasMany { return $this->hasMany(PkpaPortfolioSectionRecord::class); }
    public function caseReports(): HasMany { return $this->hasMany(PkpaPortfolioCaseReport::class); }
    public function weeklyReflections(): HasMany { return $this->hasMany(PkpaPortfolioWeeklyReflection::class); }
    public function selfAssessments(): HasMany { return $this->hasMany(PkpaPortfolioSelfAssessment::class); }
    public function documentationItems(): HasMany { return $this->hasMany(PkpaPortfolioDocumentationItem::class); }
    public function reviews(): HasMany { return $this->hasMany(PkpaPortfolioReview::class); }
    public function publications(): HasMany { return $this->hasMany(PkpaPortfolioPublication::class); }
    public function exportVersions(): HasMany { return $this->hasMany(PkpaPortfolioExportVersion::class); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draf',
            'in_progress' => 'Sedang Diisi',
            'submitted_to_field_supervisor' => 'Dikirim ke Pembimbing Lapangan',
            'field_revision_requested' => 'Revisi dari Pembimbing Lapangan',
            'field_verified' => 'Terverifikasi Pembimbing Lapangan',
            'submitted_to_internal_supervisor' => 'Dikirim ke Pembimbing Dalam',
            'internal_revision_requested' => 'Revisi dari Pembimbing Dalam',
            'approved' => 'Disetujui',
            'locked' => 'Dikunci',
            'published' => 'Diterbitkan',
            'superseded' => 'Digantikan',
            'cancelled' => 'Dibatalkan',
            default => str($this->status)->replace('_', ' ')->headline()->toString(),
        };
    }
}

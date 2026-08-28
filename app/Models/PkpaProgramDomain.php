<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PkpaProgramDomain extends Model
{
    public const SELECTION_MODES = ['direct', 'choose_one'];
    public const DURATION_UNITS = ['calendar_days', 'working_days', 'weeks', 'months', 'practice_hours'];

    protected $fillable = [
        'pkpa_program_id',
        'practice_domain_id',
        'is_required',
        'selection_mode',
        'minimum_option_count',
        'duration_value',
        'duration_unit',
        'minimum_effective_days',
        'minimum_practice_hours',
        'weight_percentage',
        'sort_order',
        'instructions',
        'is_active',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'minimum_option_count' => 'integer',
            'duration_value' => 'decimal:2',
            'minimum_effective_days' => 'integer',
            'minimum_practice_hours' => 'integer',
            'weight_percentage' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id')->withTrashed();
    }

    public function operationRules(): HasMany
    {
        return $this->hasMany(PkpaRotationOperationRule::class, 'pkpa_program_domain_id');
    }

    public function activeOperationRule(): HasOne
    {
        return $this->hasOne(PkpaRotationOperationRule::class, 'pkpa_program_domain_id')->where('is_active', true);
    }

    public function competencySets(): HasMany
    {
        return $this->hasMany(PkpaCompetencySet::class, 'pkpa_program_domain_id');
    }

    public function activeCompetencySet(): HasOne
    {
        return $this->hasOne(PkpaCompetencySet::class, 'pkpa_program_domain_id')->where('status', 'active')->where('is_current', true);
    }

    public function specialTaskTemplates(): HasMany
    {
        return $this->hasMany(PkpaSpecialTaskTemplate::class, 'pkpa_program_domain_id');
    }

    public function reportTemplates(): HasMany
    {
        return $this->hasMany(PkpaRotationReportTemplate::class, 'pkpa_program_domain_id');
    }

    public function activeReportTemplate(): HasOne
    {
        return $this->hasOne(PkpaRotationReportTemplate::class, 'pkpa_program_domain_id')->where('status', 'active')->where('is_current', true);
    }

    public function assessmentSchemes(): HasMany
    {
        return $this->hasMany(PkpaAssessmentScheme::class, 'pkpa_program_domain_id');
    }

    public function activeAssessmentScheme(): HasOne
    {
        return $this->hasOne(PkpaAssessmentScheme::class, 'pkpa_program_domain_id')->where('status', 'active')->where('is_current', true);
    }

    public function isDurationComplete(): bool
    {
        return ! is_null($this->duration_value)
            && (float) $this->duration_value > 0
            && in_array($this->duration_unit, self::DURATION_UNITS, true);
    }

    public function selectionModeLabel(): string
    {
        return $this->selection_mode === 'choose_one' ? 'Pilih satu' : 'Langsung';
    }

    public function durationLabel(): string
    {
        if (! $this->isDurationComplete()) {
            return 'Belum diisi';
        }

        return rtrim(rtrim((string) $this->duration_value, '0'), '.').' '.$this->durationUnitLabel();
    }

    public function durationUnitLabel(): string
    {
        return match ($this->duration_unit) {
            'calendar_days' => 'hari kalender',
            'working_days' => 'hari kerja',
            'weeks' => 'minggu',
            'months' => 'bulan',
            'practice_hours' => 'jam praktik',
            default => '-',
        };
    }
}

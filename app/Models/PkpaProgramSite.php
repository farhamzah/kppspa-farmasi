<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaProgramSite extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'ready', 'active', 'suspended', 'inactive', 'archived'];

    protected $fillable = [
        'pkpa_program_id',
        'practice_site_id',
        'pkpa_program_domain_id',
        'practice_domain_id',
        'practice_domain_option_id',
        'status',
        'is_active',
        'registration_notes',
        'operational_notes',
        'requirements_notes',
        'default_minimum_students',
        'default_maximum_students',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'activated_by_core_user_id',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_minimum_students' => 'integer',
            'default_maximum_students' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function practiceSite(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeSite::class, 'practice_site_id');
    }

    public function programDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaProgramDomain::class, 'pkpa_program_domain_id');
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function practiceDomainOption(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomainOption::class, 'practice_domain_option_id');
    }

    public function availabilityPeriods(): HasMany
    {
        return $this->hasMany(PkpaSiteAvailabilityPeriod::class, 'pkpa_program_site_id')->orderBy('start_date');
    }

    public function rotationAssignments(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignment::class, 'pkpa_program_site_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $builder) => $builder->whereHas('practiceSite', fn (Builder $site) => $site
            ->where('name', 'like', '%'.$search.'%')
            ->orWhere('code', 'like', '%'.$search.'%')
            ->orWhere('city', 'like', '%'.$search.'%')));
    }

    public function totalPlannedCapacity(): int
    {
        return (int) $this->availabilityPeriods()->whereIn('status', ['available', 'full'])->sum('maximum_students');
    }

    public function activeFieldSupervisorsCount(): int
    {
        return PkpaSiteFieldSupervisor::query()
            ->where('practice_site_id', $this->practice_site_id)
            ->where('status', 'active')
            ->count();
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->headline()->toString();
    }
}

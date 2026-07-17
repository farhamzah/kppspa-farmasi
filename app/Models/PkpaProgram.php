<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaProgram extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'ready', 'active', 'completed', 'archived'];

    protected $fillable = [
        'code',
        'name',
        'academic_year',
        'cohort_name',
        'semester',
        'start_date',
        'end_date',
        'registration_start_at',
        'registration_end_at',
        'status',
        'description',
        'is_active',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'activated_by_core_user_id',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_start_at' => 'datetime',
            'registration_end_at' => 'datetime',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(PkpaProgramDomain::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(PkpaEnrollment::class);
    }

    public function studentGroups(): HasMany
    {
        return $this->hasMany(PkpaStudentGroup::class);
    }

    public function programSites(): HasMany
    {
        return $this->hasMany(PkpaProgramSite::class, 'pkpa_program_id');
    }

    public function internalSupervisorEligibilities(): HasMany
    {
        return $this->hasMany(PkpaInternalSupervisorEligibility::class, 'pkpa_program_id');
    }

    public function placementPlans(): HasMany
    {
        return $this->hasMany(PkpaPlacementPlan::class, 'pkpa_program_id')->orderByDesc('version_number');
    }

    public function currentPlacementPlan()
    {
        return $this->hasOne(PkpaPlacementPlan::class, 'pkpa_program_id')->where('is_current', true);
    }

    public function placementPublications(): HasMany
    {
        return $this->hasMany(PkpaPlacementPublication::class, 'pkpa_program_id')->orderByDesc('publication_number');
    }

    public function currentPlacementPublication()
    {
        return $this->hasOne(PkpaPlacementPublication::class, 'pkpa_program_id')->where('is_current', true)->where('status', 'published');
    }

    public function rotationRuns(): HasMany
    {
        return $this->hasMany(PkpaRotationRun::class, 'pkpa_program_id');
    }

    public function activeDomains(): HasMany
    {
        return $this->domains()->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $builder) => $builder->where(fn (Builder $sub) => $sub
            ->where('code', 'like', '%'.$search.'%')
            ->orWhere('name', 'like', '%'.$search.'%')
            ->orWhere('cohort_name', 'like', '%'.$search.'%')));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'ready' => 'Siap diaktifkan',
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'archived' => 'Diarsipkan',
            default => 'Draft',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'ready' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-100',
            'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
            'completed' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            'archived' => 'bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200',
            default => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
        };
    }

    public function completionLabel(): string
    {
        if ($this->status === 'active') {
            return 'Aktif';
        }

        if (in_array($this->status, ['completed', 'archived'], true)) {
            return $this->statusLabel();
        }

        return $this->isReadyForActivation() ? 'Siap diaktifkan' : 'Belum lengkap';
    }

    public function isReadyForActivation(): bool
    {
        return app(\App\Services\PkpaProgramService::class)->readiness($this->loadMissing('domains.practiceDomain.options'))['ready'];
    }
}

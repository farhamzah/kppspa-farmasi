<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPracticeSite extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'inactive', 'expired'];

    protected $fillable = [
        'practice_domain_id',
        'practice_domain_option_id',
        'code',
        'name',
        'legal_name',
        'description',
        'address',
        'village',
        'district',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'website',
        'contact_person_name',
        'contact_person_phone',
        'cooperation_start_date',
        'cooperation_end_date',
        'status',
        'is_active',
        'notes',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'cooperation_start_date' => 'date',
            'cooperation_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function practiceDomainOption(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomainOption::class, 'practice_domain_option_id');
    }

    public function programSites(): HasMany
    {
        return $this->hasMany(PkpaProgramSite::class, 'practice_site_id');
    }

    public function fieldSupervisors(): HasMany
    {
        return $this->hasMany(PkpaSiteFieldSupervisor::class, 'practice_site_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $builder) => $builder->where(fn (Builder $sub) => $sub
            ->where('code', 'like', '%'.$search.'%')
            ->orWhere('name', 'like', '%'.$search.'%')
            ->orWhere('legal_name', 'like', '%'.$search.'%')
            ->orWhere('city', 'like', '%'.$search.'%')));
    }

    public function cooperationStatusLabel(): string
    {
        if ($this->cooperation_end_date && $this->cooperation_end_date->isPast()) {
            return 'Berakhir';
        }

        if ($this->cooperation_end_date && $this->cooperation_end_date->diffInDays(now(), false) >= -90) {
            return 'Akan berakhir';
        }

        return 'Berlaku';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            'expired' => 'Expired',
            default => 'Draf',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
            'inactive' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
            'expired' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-100',
            default => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
        };
    }
}

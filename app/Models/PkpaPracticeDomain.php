<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPracticeDomain extends Model
{
    use SoftDeletes;

    public const DEFAULT_CODES = ['APT', 'PKM', 'PBF', 'RS', 'IND', 'PEM'];

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'description',
        'is_system',
        'is_active',
        'sort_order',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(PkpaPracticeDomainOption::class, 'practice_domain_id')->orderBy('sort_order')->orderBy('name');
    }

    public function activeOptions(): HasMany
    {
        return $this->options()->where('is_active', true);
    }

    public function programDomains(): HasMany
    {
        return $this->hasMany(PkpaProgramDomain::class, 'practice_domain_id');
    }

    public function practiceSites(): HasMany
    {
        return $this->hasMany(PkpaPracticeSite::class, 'practice_domain_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isGovernment(): bool
    {
        return $this->code === 'PEM';
    }
}

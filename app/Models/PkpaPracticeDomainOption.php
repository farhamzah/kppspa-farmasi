<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaPracticeDomainOption extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'practice_domain_id',
        'code',
        'name',
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

    public function practiceDomain(): BelongsTo
    {
        return $this->belongsTo(PkpaPracticeDomain::class, 'practice_domain_id');
    }

    public function practiceSites(): HasMany
    {
        return $this->hasMany(PkpaPracticeSite::class, 'practice_domain_option_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isProtectedSystemOption(): bool
    {
        $this->loadMissing('practiceDomain');

        return $this->is_system
            || ($this->practiceDomain?->isGovernment() && in_array($this->code, PkpaPracticeDomain::GOVERNMENT_OPTION_CODES, true));
    }
}

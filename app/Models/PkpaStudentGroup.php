<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaStudentGroup extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'inactive', 'archived'];

    protected $fillable = [
        'pkpa_program_id',
        'code',
        'name',
        'description',
        'maximum_members',
        'status',
        'is_active',
        'created_by_core_user_id',
        'updated_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'maximum_members' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PkpaStudentGroupMember::class, 'pkpa_student_group_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active')->whereNull('left_at');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $builder) => $builder->where(fn (Builder $sub) => $sub
            ->where('code', 'like', '%'.$search.'%')
            ->orWhere('name', 'like', '%'.$search.'%')));
    }

    public function remainingCapacity(): ?int
    {
        if (is_null($this->maximum_members)) {
            return null;
        }

        return max(0, $this->maximum_members - $this->activeMembers()->count());
    }
}

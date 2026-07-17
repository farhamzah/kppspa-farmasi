<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PkpaEnrollment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'on_hold', 'cancelled', 'completed', 'archived'];

    protected $fillable = [
        'pkpa_program_id',
        'core_user_id',
        'student_number',
        'student_name_snapshot',
        'student_email_snapshot',
        'study_program_snapshot',
        'cohort_snapshot',
        'academic_status_snapshot',
        'core_account_status_snapshot',
        'status',
        'enrolled_at',
        'activated_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'last_core_synced_at',
        'last_core_sync_status',
        'last_core_sync_message',
        'created_by_core_user_id',
        'updated_by_core_user_id',
        'cancelled_by_core_user_id',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_core_synced_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(PkpaProgram::class, 'pkpa_program_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(PkpaEnrollmentRequirement::class)->orderBy('id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(PkpaStudentGroupMember::class);
    }

    public function rotationAssignments(): HasMany
    {
        return $this->hasMany(PkpaRotationAssignment::class, 'pkpa_enrollment_id');
    }

    public function activeGroupMembership(): HasOne
    {
        return $this->hasOne(PkpaStudentGroupMember::class)->where('status', 'active')->whereNull('left_at')->latestOfMany();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $builder) => $builder->where(fn (Builder $sub) => $sub
            ->where('core_user_id', 'like', '%'.$search.'%')
            ->orWhere('student_number', 'like', '%'.$search.'%')
            ->orWhere('student_name_snapshot', 'like', '%'.$search.'%')));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'on_hold' => 'Ditahan',
            'cancelled' => 'Dibatalkan',
            'completed' => 'Selesai',
            'archived' => 'Diarsipkan',
            default => 'Draft',
        };
    }

    public function requirementSummary(): string
    {
        $total = $this->requirements->count();
        $completed = $this->requirements->where('status', 'completed')->count();

        return "{$completed} dari {$total} selesai";
    }
}

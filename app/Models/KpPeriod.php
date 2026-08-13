<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpPeriod extends Model
{
    public const STATUSES = ['draft', 'dibuka', 'ditutup', 'selesai'];

    protected $fillable = [
        'name',
        'academic_year',
        'semester',
        'registration_start_at',
        'registration_end_at',
        'document_verification_start_at',
        'document_verification_end_at',
        'selection_start_at',
        'selection_end_at',
        'kp_start_date',
        'kp_end_date',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_start_at' => 'datetime',
            'registration_end_at' => 'datetime',
            'document_verification_start_at' => 'datetime',
            'document_verification_end_at' => 'datetime',
            'selection_start_at' => 'datetime',
            'selection_end_at' => 'datetime',
            'kp_start_date' => 'date',
            'kp_end_date' => 'date',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function quotas()
    {
        return $this->hasMany(KpPlaceQuota::class);
    }

    public function documentRequirements()
    {
        return $this->hasMany(KpDocumentRequirement::class, 'kp_period_id')->orderBy('sort_order')->orderBy('name');
    }

    public function registrations()
    {
        return $this->hasMany(KpRegistration::class, 'kp_period_id');
    }

    public function placeSelections()
    {
        return $this->hasMany(KpPlaceSelection::class, 'kp_period_id');
    }

    public function waitingLists()
    {
        return $this->hasMany(KpWaitingList::class, 'kp_period_id');
    }

    public function assignments()
    {
        return $this->hasMany(KpAssignment::class, 'kp_period_id');
    }

    public function assessmentComponents()
    {
        return $this->hasMany(KpAssessmentComponent::class, 'kp_period_id');
    }

    public function competencies()
    {
        return $this->hasMany(KpCompetency::class, 'kp_period_id')->orderBy('sort_order')->orderBy('title');
    }

    public function places()
    {
        return $this->belongsToMany(KpPlace::class, 'kp_place_quotas')->withPivot(['quota', 'is_open', 'notes'])->withTimestamps();
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === 'dibuka'
            && $this->registration_start_at
            && $this->registration_end_at
            && now()->between($this->registration_start_at, $this->registration_end_at);
    }

    public function isSelectionOpen(): bool
    {
        return $this->status === 'dibuka'
            && $this->selection_start_at
            && $this->selection_end_at
            && now()->between($this->selection_start_at, $this->selection_end_at);
    }

    public function isActive(): bool
    {
        return $this->status === 'dibuka';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'dibuka' => 'Dibuka',
            'ditutup' => 'Ditutup',
            'selesai' => 'Selesai',
            default => 'Draf',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'dibuka' => 'bg-emerald-50 text-emerald-700',
            'ditutup' => 'bg-amber-50 text-amber-700',
            'selesai' => 'bg-slate-100 text-slate-700',
            default => 'bg-sky-50 text-sky-700',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpRegistration extends Model
{
    protected $fillable = [
        'kp_period_id',
        'student_id',
        'registration_number',
        'status',
        'notes',
        'submitted_at',
        'verified_by',
        'verified_at',
        'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function period()
    {
        return $this->belongsTo(KpPeriod::class, 'kp_period_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents()
    {
        return $this->hasMany(KpDocument::class, 'kp_registration_id');
    }

    public function logs()
    {
        return $this->hasMany(KpRegistrationLog::class, 'kp_registration_id');
    }

    public function placeSelections()
    {
        return $this->hasMany(KpPlaceSelection::class, 'kp_registration_id');
    }

    public function activePlaceSelection()
    {
        return $this->hasOne(KpPlaceSelection::class, 'kp_registration_id')->where('status', 'aktif');
    }

    public function waitingList()
    {
        return $this->hasOne(KpWaitingList::class, 'kp_registration_id');
    }

    public function assignment()
    {
        return $this->hasOne(KpAssignment::class, 'kp_registration_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'menunggu_verifikasi' => 'Menunggu Verifikasi',
            'revisi' => 'Revisi',
            'terverifikasi' => 'Terverifikasi',
            'ditolak' => 'Ditolak',
            'dibatalkan' => 'Dibatalkan',
            default => 'Draf',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'menunggu_verifikasi' => 'bg-sky-50 text-sky-700',
            'revisi' => 'bg-amber-50 text-amber-700',
            'terverifikasi' => 'bg-emerald-50 text-emerald-700',
            'ditolak', 'dibatalkan' => 'bg-rose-50 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['menunggu_verifikasi', 'revisi', 'terverifikasi', 'ditolak'], true);
    }

    public function isWaitingVerification(): bool
    {
        return $this->status === 'menunggu_verifikasi';
    }

    public function isVerified(): bool
    {
        return $this->status === 'terverifikasi';
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, ['draft', 'revisi'], true) && $this->requiredDocumentsCompleted();
    }

    public function isEligibleForPlaceSelection(): bool
    {
        return $this->isVerified() && $this->allRequiredDocumentsApproved();
    }

    public function selectedPlace(): ?KpPlace
    {
        return $this->activePlaceSelection()->with('place')->first()?->place;
    }

    public function selectionStatusLabel(): string
    {
        if ($this->activePlaceSelection()->exists()) {
            return 'Sudah memilih tempat';
        }

        if ($this->waitingList()->where('status', 'menunggu')->exists()) {
            return 'Daftar tunggu';
        }

        return $this->isEligibleForPlaceSelection() ? 'Siap memilih' : 'Belum eligible';
    }

    public function requiredDocumentsCompleted(): bool
    {
        $requirements = $this->period?->documentRequirements()->where('status', 'aktif')->where('is_required', true)->get() ?? collect();

        return $requirements->every(fn ($requirement) => $this->documents->firstWhere('kp_document_requirement_id', $requirement->id)?->file_path);
    }

    public function allRequiredDocumentsApproved(): bool
    {
        $requirements = $this->period?->documentRequirements()->where('status', 'aktif')->where('is_required', true)->get() ?? collect();

        return $requirements->every(fn ($requirement) => $this->documents->firstWhere('kp_document_requirement_id', $requirement->id)?->status === 'disetujui');
    }

    public function progressPercentage(): int
    {
        $requirements = $this->period?->documentRequirements()->where('status', 'aktif')->get() ?? collect();

        if ($requirements->isEmpty()) {
            return 0;
        }

        $uploaded = $requirements->filter(fn ($requirement) => $this->documents->firstWhere('kp_document_requirement_id', $requirement->id)?->file_path)->count();

        return (int) round(($uploaded / $requirements->count()) * 100);
    }
}

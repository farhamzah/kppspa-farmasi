<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpPlaceQuota extends Model
{
    private bool $filledCountResolved = false;

    private int $resolvedFilledCount = 0;

    protected $fillable = [
        'kp_period_id',
        'kp_place_id',
        'quota',
        'is_open',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
        ];
    }

    public function period()
    {
        return $this->belongsTo(KpPeriod::class, 'kp_period_id');
    }

    public function place()
    {
        return $this->belongsTo(KpPlace::class, 'kp_place_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logs()
    {
        return $this->hasMany(KpQuotaLog::class, 'kp_place_quota_id');
    }

    public function selections()
    {
        return $this->hasMany(KpPlaceSelection::class, 'kp_place_quota_id');
    }

    public function filledCount(): int
    {
        if ($this->filledCountResolved) {
            return $this->resolvedFilledCount;
        }

        $officialCount = $this->officialPkpaFilledCount();
        $this->resolvedFilledCount = $officialCount ?? $this->selections()->where('status', 'aktif')->count();
        $this->filledCountResolved = true;

        return $this->resolvedFilledCount;
    }

    public function remainingQuota(): int
    {
        return max(0, $this->quota - $this->filledCount());
    }

    public function isFull(): bool
    {
        return $this->remainingQuota() <= 0;
    }

    public function statusLabel(): string
    {
        if ($this->isFull()) {
            return 'Penuh';
        }

        return $this->is_open ? 'Dibuka' : 'Ditutup';
    }

    public function statusBadgeClass(): string
    {
        if ($this->isFull()) {
            return 'bg-rose-50 text-rose-700';
        }

        return $this->is_open ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700';
    }

    private function officialPkpaFilledCount(): ?int
    {
        $this->loadMissing(['period', 'place']);

        if (! $this->period || ! $this->place) {
            return null;
        }

        $programCode = trim(str($this->period->name)->before(' - ')->toString());
        $program = PkpaProgram::query()->where('code', $programCode)->first();

        if (! $program) {
            return null;
        }

        $domainCode = match ($this->place->type) {
            'apotek' => 'APT',
            'distributor' => 'PBF',
            'rumah_sakit' => 'RS',
            'industri' => 'IND',
            'puskesmas', 'lainnya' => 'PEM',
            default => null,
        };

        $site = PkpaPracticeSite::query()
            ->where('name', $this->place->name)
            ->when($domainCode, fn ($query) => $query->whereHas('practiceDomain', fn ($domain) => $domain->where('code', $domainCode)))
            ->first();

        if (! $site) {
            return null;
        }

        $publication = $program->currentPlacementPublication()->first()
            ?? $program->placementPublications()->where('status', 'published')->first();

        if (! $publication) {
            return 0;
        }

        return $publication->assignments()->where('practice_site_id', $site->id)->count();
    }
}

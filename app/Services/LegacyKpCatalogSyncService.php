<?php

namespace App\Services;

use App\Models\KpPeriod;
use App\Models\KpPlace;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\User;

class LegacyKpCatalogSyncService
{
    public function sync(?User $actor = null): void
    {
        $this->syncPeriods($actor);
        $this->syncPlaces($actor);
    }

    private function syncPeriods(?User $actor = null): void
    {
        PkpaProgram::query()
            ->whereIn('status', ['draft', 'ready', 'active', 'completed'])
            ->orderByDesc('id')
            ->get()
            ->each(function (PkpaProgram $program) use ($actor): void {
                KpPeriod::updateOrCreate(
                    ['name' => trim($program->code.' - '.$program->name)],
                    [
                        'academic_year' => $program->academic_year,
                        'semester' => $this->mapSemester($program->semester),
                        'kp_start_date' => $program->start_date,
                        'kp_end_date' => $program->end_date,
                        'status' => $this->mapProgramStatus($program->status),
                        'description' => $program->description,
                        'created_by' => $actor?->id,
                        'updated_by' => $actor?->id,
                    ]
                );
            });
    }

    private function syncPlaces(?User $actor = null): void
    {
        PkpaPracticeSite::query()
            ->with(['practiceDomain', 'practiceDomainOption'])
            ->where('is_active', true)
            ->whereIn('status', ['active', 'inactive'])
            ->orderBy('name')
            ->get()
            ->each(function (PkpaPracticeSite $site) use ($actor): void {
                KpPlace::updateOrCreate(
                    [
                        'name' => $site->name,
                        'type' => $this->mapPlaceType($site),
                    ],
                    [
                        'address' => $site->address,
                        'city' => $site->city,
                        'province' => $site->province,
                        'contact_person' => $site->contact_person_name,
                        'phone' => $site->contact_person_phone ?: $site->phone,
                        'email' => $site->email,
                        'description' => $site->description,
                        'status' => $site->status === 'active' ? 'aktif' : 'nonaktif',
                        'created_by' => $actor?->id,
                        'updated_by' => $actor?->id,
                    ]
                );
            });
    }

    private function mapProgramStatus(?string $status): string
    {
        return match ($status) {
            'active' => 'dibuka',
            'completed' => 'selesai',
            'archived' => 'ditutup',
            default => 'draft',
        };
    }

    private function mapSemester(?string $semester): ?string
    {
        return in_array($semester, ['ganjil', 'genap', 'antara'], true) ? $semester : null;
    }

    private function mapPlaceType(PkpaPracticeSite $site): string
    {
        $domainCode = strtoupper((string) $site->practiceDomain?->code);
        $optionCode = strtoupper((string) $site->practiceDomainOption?->code);

        return match (true) {
            $optionCode === 'PUSKESMAS' => 'puskesmas',
            $domainCode === 'APT' => 'apotek',
            $domainCode === 'RS' => 'rumah_sakit',
            $domainCode === 'IND' => 'industri',
            $domainCode === 'PBF' => 'distributor',
            $domainCode === 'PEM' => 'lainnya',
            default => 'lainnya',
        };
    }
}

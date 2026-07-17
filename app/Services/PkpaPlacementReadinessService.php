<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;

class PkpaPlacementReadinessService
{
    public function check(PkpaProgram $program): array
    {
        $program->loadMissing([
            'domains.practiceDomain',
            'programSites.practiceSite.fieldSupervisors',
            'programSites.availabilityPeriods',
            'enrollments',
            'studentGroups',
        ]);

        $participantCount = PkpaEnrollment::where('pkpa_program_id', $program->id)->whereIn('status', ['active', 'on_hold'])->count();
        $domainCards = [];
        $critical = 0;
        $warnings = 0;

        foreach ($program->domains()->with('practiceDomain')->where('is_active', true)->orderBy('sort_order')->get() as $programDomain) {
            $sites = $program->programSites->where('practice_domain_id', $programDomain->practice_domain_id)->where('is_active', true)->whereIn('status', ['ready', 'active']);
            $availability = $sites->flatMap->availabilityPeriods->whereIn('status', ['available', 'full']);
            $capacity = (int) $availability->sum('maximum_students');
            $internalCount = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $program->id)
                ->where('practice_domain_id', $programDomain->practice_domain_id)
                ->where('status', 'active')
                ->count();
            $fieldMissing = $sites->filter(fn ($site) => $site->activeFieldSupervisorsCount() === 0)->count();

            $issues = [];
            $warns = [];
            if ($sites->isEmpty()) {
                $issues[] = 'Belum ada tempat aktif.';
            }
            if ($availability->isEmpty()) {
                $issues[] = 'Belum ada availability period.';
            }
            if ($capacity <= 0) {
                $issues[] = 'Kapasitas terencana belum tersedia.';
            }
            if ($capacity < $participantCount) {
                $issues[] = 'Kapasitas kurang dari jumlah peserta.';
            }
            if ($internalCount === 0) {
                $issues[] = 'Belum ada Pembimbing Dalam eligible.';
            }
            if ($fieldMissing > 0) {
                $issues[] = $fieldMissing.' tempat belum memiliki Pembimbing Lapangan aktif.';
            }
            if ($programDomain->practiceDomain?->isGovernment()) {
                $hasGovernmentOption = $sites->contains(fn ($site) => filled($site->practice_domain_option_id));
                if (! $hasGovernmentOption) {
                    $issues[] = 'Tempat Pemerintahan belum memiliki Loka POM/Dinas Kesehatan.';
                }
            }
            $unsynced = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $program->id)
                ->where('practice_domain_id', $programDomain->practice_domain_id)
                ->where(fn ($query) => $query->whereNull('last_core_synced_at')->orWhere('last_core_synced_at', '<', now()->subDays(30)))
                ->count();
            if ($unsynced > 0) {
                $warns[] = $unsynced.' Pembimbing Dalam perlu sinkronisasi ulang.';
            }

            $critical += count($issues);
            $warnings += count($warns);
            $domainCards[] = [
                'domain' => $programDomain->practiceDomain,
                'sites' => $sites->count(),
                'availability' => $availability->count(),
                'capacity' => $capacity,
                'participants' => $participantCount,
                'internal_supervisors' => $internalCount,
                'field_missing' => $fieldMissing,
                'issues' => $issues,
                'warnings' => $warns,
                'status' => count($issues) ? 'Belum siap' : (count($warns) ? 'Perlu perhatian' : 'Siap menyusun penempatan'),
            ];
        }

        $ungrouped = PkpaEnrollment::where('pkpa_program_id', $program->id)->where('status', 'active')->whereDoesntHave('activeGroupMembership')->count();
        if ($ungrouped > 0) {
            $warnings++;
        }

        return [
            'summary' => [
                'participants' => $participantCount,
                'groups' => $program->studentGroups()->where('is_active', true)->count(),
                'active_sites' => $program->programSites()->where('is_active', true)->whereIn('status', ['ready', 'active'])->count(),
                'availability_periods' => $program->programSites()->withCount('availabilityPeriods')->get()->sum('availability_periods_count'),
                'internal_supervisors' => PkpaInternalSupervisorEligibility::where('pkpa_program_id', $program->id)->where('status', 'active')->count(),
                'field_supervisors' => $program->programSites->sum(fn ($site) => $site->activeFieldSupervisorsCount()),
                'ungrouped_participants' => $ungrouped,
                'critical' => $critical,
                'warnings' => $warnings,
            ],
            'domains' => $domainCards,
            'status' => $critical > 0 ? 'Belum siap' : ($warnings > 0 ? 'Perlu perhatian' : 'Siap menyusun penempatan'),
        ];
    }
}

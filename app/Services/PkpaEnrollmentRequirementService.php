<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaProgramDomain;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PkpaEnrollmentRequirementService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function ensureRequirements(PkpaEnrollment $enrollment, ?User $actor = null): void
    {
        $programDomains = PkpaProgramDomain::query()
            ->with('practiceDomain')
            ->where('pkpa_program_id', $enrollment->pkpa_program_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($programDomains->isEmpty()) {
            throw ValidationException::withMessages(['program' => 'Program belum memiliki konfigurasi wahana aktif.']);
        }

        foreach ($programDomains as $programDomain) {
            $requirement = $enrollment->requirements()->firstOrCreate(
                ['pkpa_program_domain_id' => $programDomain->id],
                [
                    'practice_domain_id' => $programDomain->practice_domain_id,
                    'selection_mode' => $programDomain->selection_mode,
                    'required_option_count' => $programDomain->minimum_option_count,
                    'selected_practice_domain_option_id' => null,
                    'status' => 'pending',
                    'completion_percentage' => 0,
                    'created_by_core_user_id' => $actor?->core_user_id,
                    'updated_by_core_user_id' => $actor?->core_user_id,
                ]
            );

            if ($requirement->wasRecentlyCreated) {
                $this->audit->record($actor, 'enrollment_requirement_created', $requirement, null, $requirement->only(['pkpa_enrollment_id', 'practice_domain_id', 'selection_mode']));
            }
        }
    }

    public function missingCount(PkpaEnrollment $enrollment): int
    {
        $expected = PkpaProgramDomain::where('pkpa_program_id', $enrollment->pkpa_program_id)->where('is_active', true)->count();

        return max(0, $expected - $enrollment->requirements()->count());
    }
}

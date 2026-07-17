<?php

namespace App\Services;

use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaProgramSiteService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function create(PkpaProgram $program, PkpaPracticeSite $site, array $data, ?User $actor): PkpaProgramSite
    {
        if (in_array($program->status, ['completed', 'archived'], true)) {
            throw ValidationException::withMessages(['pkpa_program_id' => 'Program tidak menerima tempat baru.']);
        }

        if (! $site->is_active || $site->status !== 'active') {
            throw ValidationException::withMessages(['practice_site_id' => 'Tempat praktik nonaktif tidak dapat ditambahkan ke program.']);
        }

        if ($site->cooperation_end_date && $site->cooperation_end_date->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['practice_site_id' => 'Kerja sama tempat praktik sudah berakhir.']);
        }

        $programDomain = $program->domains()->where('practice_domain_id', $site->practice_domain_id)->where('is_active', true)->first();
        if (! $programDomain) {
            throw ValidationException::withMessages(['practice_site_id' => 'Domain tempat tidak sesuai dengan konfigurasi wahana program.']);
        }

        if ((int) ($site->practice_domain_option_id ?? 0) !== (int) ($data['practice_domain_option_id'] ?? $site->practice_domain_option_id ?? 0)) {
            throw ValidationException::withMessages(['practice_domain_option_id' => 'Subjenis tempat harus sama dengan master tempat.']);
        }

        if (PkpaProgramSite::withTrashed()->where('pkpa_program_id', $program->id)->where('practice_site_id', $site->id)->exists()) {
            throw ValidationException::withMessages(['practice_site_id' => 'Tempat sudah terhubung dengan program ini.']);
        }

        return DB::transaction(function () use ($program, $site, $programDomain, $data, $actor) {
            $programSite = PkpaProgramSite::create([
                'pkpa_program_id' => $program->id,
                'practice_site_id' => $site->id,
                'pkpa_program_domain_id' => $programDomain->id,
                'practice_domain_id' => $site->practice_domain_id,
                'practice_domain_option_id' => $site->practice_domain_option_id,
                'status' => $data['status'] ?? 'active',
                'is_active' => (bool) ($data['is_active'] ?? true),
                'registration_notes' => $data['registration_notes'] ?? null,
                'operational_notes' => $data['operational_notes'] ?? null,
                'requirements_notes' => $data['requirements_notes'] ?? null,
                'default_minimum_students' => $data['default_minimum_students'] ?? null,
                'default_maximum_students' => $data['default_maximum_students'] ?? null,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
                'activated_by_core_user_id' => ($data['status'] ?? 'active') === 'active' ? $actor?->core_user_id : null,
                'activated_at' => ($data['status'] ?? 'active') === 'active' ? now() : null,
            ]);
            $this->audit->record($actor, 'program_site_created', $programSite, null, $programSite->only(['pkpa_program_id', 'practice_site_id', 'status']));

            return $programSite->load(['program', 'practiceSite', 'practiceDomain', 'practiceDomainOption']);
        });
    }

    public function update(PkpaProgramSite $programSite, array $data, ?User $actor): PkpaProgramSite
    {
        $old = $programSite->only(array_keys($data));
        $programSite->update($data + ['updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'program_site_updated', $programSite, $old, $programSite->only(array_keys($data)));

        return $programSite->refresh();
    }

    public function deactivate(PkpaProgramSite $programSite, ?User $actor): PkpaProgramSite
    {
        return DB::transaction(function () use ($programSite, $actor) {
            $old = $programSite->only(['status', 'is_active']);
            $programSite->update(['status' => 'inactive', 'is_active' => false, 'updated_by_core_user_id' => $actor?->core_user_id]);
            $this->audit->record($actor, 'program_site_deactivated', $programSite, $old, $programSite->only(['status', 'is_active']));

            return $programSite->refresh();
        });
    }
}

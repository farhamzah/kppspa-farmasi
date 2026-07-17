<?php

namespace App\Services;

use App\Models\PkpaFinalAssessmentComponent;
use App\Models\PkpaFinalAssessmentScheme;
use App\Models\PkpaProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaFinalAssessmentSchemeService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function create(PkpaProgram $program, array $data, ?User $actor): PkpaFinalAssessmentScheme
    {
        $this->ensureCoordinator($actor);
        $scheme = PkpaFinalAssessmentScheme::create(array_merge($data, [
            'pkpa_program_id' => $program->id,
            'version_number' => $data['version_number'] ?? ((int) PkpaFinalAssessmentScheme::where('pkpa_program_id', $program->id)->where('code', $data['code'])->max('version_number') + 1),
            'maximum_score' => $data['maximum_score'] ?? 100,
            'rounding_precision' => $data['rounding_precision'] ?? 2,
            'rounding_mode' => $data['rounding_mode'] ?? 'half_up',
            'status' => $data['status'] ?? 'draft',
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]));
        $this->audit->record($actor, 'pkpa_final_scheme_created', $scheme);

        return $scheme;
    }

    public function saveComponent(PkpaFinalAssessmentScheme $scheme, array $data, ?User $actor): PkpaFinalAssessmentComponent
    {
        $this->ensureCoordinator($actor);
        if (in_array($scheme->status, ['active', 'superseded', 'archived'], true)) {
            throw ValidationException::withMessages(['scheme' => 'Skema aktif atau historis tidak dapat diedit destruktif.']);
        }
        if (($data['component_type'] ?? null) === 'wahana_grade' && empty($data['source_practice_domain_id'])) {
            throw ValidationException::withMessages(['source_practice_domain_id' => 'Komponen wahana wajib memilih domain praktik.']);
        }
        if (($data['component_type'] ?? null) === 'program_assessment' && empty($data['source_program_assessment_template_id'])) {
            throw ValidationException::withMessages(['source_program_assessment_template_id' => 'Komponen program wajib memilih template evaluasi.']);
        }

        return tap(PkpaFinalAssessmentComponent::updateOrCreate(
            ['pkpa_final_assessment_scheme_id' => $scheme->id, 'code' => $data['code']],
            array_merge($data, ['status' => $data['status'] ?? 'active', 'created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($component) => $this->audit->record($actor, 'pkpa_final_component_saved', $component));
    }

    public function activate(PkpaFinalAssessmentScheme $scheme, ?User $actor): PkpaFinalAssessmentScheme
    {
        $this->ensureCoordinator($actor);
        $components = $scheme->components()->where('status', 'active')->get();
        $weight = $components->sum(fn ($component) => (int) round(((float) $component->weight_percentage) * 10000));
        if ($components->isEmpty() || $weight !== 1000000) {
            throw ValidationException::withMessages(['weight_percentage' => 'Total bobot komponen aktif harus tepat 100%.']);
        }
        $duplicateDomain = $components->where('component_type', 'wahana_grade')->groupBy('source_practice_domain_id')->contains(fn ($items) => $items->count() > 1);
        if ($duplicateDomain) {
            throw ValidationException::withMessages(['source_practice_domain_id' => 'Satu wahana tidak boleh muncul dua kali dalam skema akhir.']);
        }

        return DB::transaction(function () use ($scheme, $actor) {
            PkpaFinalAssessmentScheme::where('pkpa_program_id', $scheme->pkpa_program_id)->where('is_current', true)->whereKeyNot($scheme->id)->update(['is_current' => false, 'current_key' => null, 'status' => 'superseded']);
            $scheme->update(['status' => 'active', 'is_current' => true, 'current_key' => 'PROGRAM:'.$scheme->pkpa_program_id, 'activated_at' => now(), 'activated_by_core_user_id' => $actor?->core_user_id]);
            $this->audit->record($actor, 'pkpa_final_scheme_activated', $scheme);

            return $scheme->refresh();
        });
    }

    private function ensureCoordinator(?User $actor): void
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat mengelola skema akhir.']);
        }
    }
}

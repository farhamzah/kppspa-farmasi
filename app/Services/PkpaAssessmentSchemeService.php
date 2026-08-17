<?php

namespace App\Services;

use App\Models\PkpaAssessmentComponent;
use App\Models\PkpaAssessmentRubric;
use App\Models\PkpaAssessmentRubricCriterion;
use App\Models\PkpaAssessmentRubricLevel;
use App\Models\PkpaAssessmentScheme;
use App\Models\PkpaProgramDomain;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaAssessmentSchemeService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function createScheme(PkpaProgramDomain $programDomain, array $data, ?User $actor): PkpaAssessmentScheme
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat membuat skema penilaian.']);
        }

        $version = $data['version_number'] ?? ((int) PkpaAssessmentScheme::where('pkpa_program_domain_id', $programDomain->id)->where('code', $data['code'])->max('version_number') + 1);
        $scheme = PkpaAssessmentScheme::create(array_merge($data, [
            'pkpa_program_domain_id' => $programDomain->id,
            'version_number' => $version,
            'maximum_score' => $data['maximum_score'] ?? 100,
            'rounding_precision' => $data['rounding_precision'] ?? 2,
            'rounding_mode' => $data['rounding_mode'] ?? 'half_up',
            'status' => $data['status'] ?? 'draft',
            'hide_other_assessor_scores_until_submit' => $data['hide_other_assessor_scores_until_submit'] ?? config('my_pkpa.hide_other_assessor_scores_until_submit'),
            'require_academic_readiness' => $data['require_academic_readiness'] ?? true,
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]));
        $this->audit->record($actor, 'pkpa_assessment_scheme_created', $scheme);

        return $scheme;
    }

    public function saveComponent(PkpaAssessmentScheme $scheme, array $data, ?User $actor): PkpaAssessmentComponent
    {
        $this->ensureEditable($scheme, $actor);
        if ($this->decimalLessThan($data['weight_percentage'] ?? '0', '0')) {
            throw ValidationException::withMessages(['weight_percentage' => 'Bobot komponen tidak boleh negatif.']);
        }

        $component = PkpaAssessmentComponent::updateOrCreate(
            ['pkpa_assessment_scheme_id' => $scheme->id, 'code' => $data['code']],
            array_merge($data, [
                'status' => $data['status'] ?? 'draft',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ])
        );
        $this->audit->record($actor, 'pkpa_assessment_component_saved', $component, null, ['weight' => $component->weight_percentage]);

        return $component;
    }

    public function saveRubric(PkpaAssessmentComponent $component, array $data, ?User $actor): PkpaAssessmentRubric
    {
        $this->ensureEditable($component->scheme()->firstOrFail(), $actor);

        return tap(PkpaAssessmentRubric::updateOrCreate(
            ['pkpa_assessment_component_id' => $component->id, 'code' => $data['code']],
            array_merge($data, ['status' => $data['status'] ?? 'draft', 'created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($rubric) => $this->audit->record($actor, 'pkpa_assessment_rubric_saved', $rubric));
    }

    public function saveCriterion(PkpaAssessmentRubric $rubric, array $data, ?User $actor): PkpaAssessmentRubricCriterion
    {
        $this->ensureEditable($rubric->component()->firstOrFail()->scheme()->firstOrFail(), $actor);

        return tap(PkpaAssessmentRubricCriterion::updateOrCreate(
            ['pkpa_assessment_rubric_id' => $rubric->id, 'code' => $data['code']],
            array_merge($data, ['status' => $data['status'] ?? 'draft', 'created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($criterion) => $this->audit->record($actor, 'pkpa_assessment_criterion_saved', $criterion));
    }

    public function saveLevel(PkpaAssessmentRubricCriterion $criterion, array $data, ?User $actor): PkpaAssessmentRubricLevel
    {
        $this->ensureEditable($criterion->rubric()->firstOrFail()->component()->firstOrFail()->scheme()->firstOrFail(), $actor);
        $this->assertNoRangeOverlap($criterion, $data);

        return tap(PkpaAssessmentRubricLevel::updateOrCreate(
            ['pkpa_assessment_rubric_criterion_id' => $criterion->id, 'code' => $data['code']],
            array_merge($data, ['status' => $data['status'] ?? 'draft', 'created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($level) => $this->audit->record($actor, 'pkpa_assessment_level_saved', $level));
    }

    public function activate(PkpaAssessmentScheme $scheme, ?User $actor): PkpaAssessmentScheme
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Koordinator/Admin yang dapat mengaktifkan skema penilaian.']);
        }
        $scheme->load('components.rubrics.criteria.levels');
        $this->assertActivatable($scheme);

        return DB::transaction(function () use ($scheme, $actor) {
            PkpaAssessmentScheme::where('pkpa_program_domain_id', $scheme->pkpa_program_domain_id)
                ->where('is_current', true)
                ->whereKeyNot($scheme->id)
                ->update(['is_current' => false, 'current_key' => null, 'status' => 'superseded']);

            $scheme->update([
                'status' => 'active',
                'is_current' => true,
                'current_key' => 'PROGRAM_DOMAIN:'.$scheme->pkpa_program_domain_id,
                'activated_by_core_user_id' => $actor?->core_user_id,
                'activated_at' => now(),
            ]);
            $this->audit->record($actor, 'pkpa_assessment_scheme_activated', $scheme);

            return $scheme->refresh();
        });
    }

    private function ensureEditable(PkpaAssessmentScheme $scheme, ?User $actor): void
    {
        if (! $this->isCoordinator($actor) || in_array($scheme->status, ['active', 'superseded', 'archived'], true)) {
            throw ValidationException::withMessages(['scheme' => 'Skema aktif atau historis tidak dapat diedit destruktif. Buat versi baru.']);
        }
    }

    private function assertActivatable(PkpaAssessmentScheme $scheme): void
    {
        if (! in_array($scheme->rounding_mode, ['half_up', 'half_even', 'floor', 'ceil'], true) || $scheme->rounding_precision > 4) {
            throw ValidationException::withMessages(['rounding' => 'Konfigurasi pembulatan skema tidak valid.']);
        }
        $activeComponents = $scheme->components->where('status', 'active');
        if ($activeComponents->isEmpty()) {
            throw ValidationException::withMessages(['components' => 'Skema wajib memiliki komponen aktif.']);
        }
        $weight = $activeComponents->reduce(fn ($total, $component) => $total + (int) round(((float) $component->weight_percentage) * 10000), 0);
        if ($weight !== 1000000) {
            throw ValidationException::withMessages(['weight_percentage' => 'Total bobot komponen aktif harus tepat 100%.']);
        }
        foreach ($activeComponents as $component) {
            if ($component->calculation_method === 'rubric' && $component->rubrics->where('status', 'active')->isEmpty()) {
                throw ValidationException::withMessages(['rubric' => 'Komponen rubric wajib memiliki rubrik aktif.']);
            }
            foreach ($component->rubrics->where('status', 'active') as $rubric) {
                $criteria = $rubric->criteria->where('status', 'active');
                if ($criteria->isEmpty()) {
                    throw ValidationException::withMessages(['criteria' => 'Rubrik aktif wajib memiliki kriteria.']);
                }
                if ($rubric->scoring_method === 'weighted_criteria') {
                    $criteriaWeight = $criteria->reduce(fn ($total, $criterion) => $total + (int) round(((float) $criterion->weight_percentage) * 10000), 0);
                    if ($criteriaWeight !== 1000000) {
                        throw ValidationException::withMessages(['criteria' => 'Total bobot kriteria rubric weighted harus tepat 100%.']);
                    }
                }
            }
        }
    }

    private function assertNoRangeOverlap(PkpaAssessmentRubricCriterion $criterion, array $data): void
    {
        if (! isset($data['minimum_value'], $data['maximum_value'])) {
            return;
        }
        $min = (float) $data['minimum_value'];
        $max = (float) $data['maximum_value'];
        if ($min > $max) {
            throw ValidationException::withMessages(['minimum_value' => 'Rentang level tidak valid.']);
        }
        $overlap = $criterion->levels()
            ->where('code', '!=', $data['code'])
            ->whereNotNull('minimum_value')
            ->whereNotNull('maximum_value')
            ->get()
            ->contains(fn ($level) => $min <= (float) $level->maximum_value && $max >= (float) $level->minimum_value);
        if ($overlap) {
            throw ValidationException::withMessages(['minimum_value' => 'Rentang level rubrik tidak boleh overlap.']);
        }
    }

    private function decimalLessThan(string|int|float $value, string $minimum): bool
    {
        return (float) $value < (float) $minimum;
    }
}

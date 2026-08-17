<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaFinalAssessmentScheme;
use App\Models\PkpaFinalGradeCalculation;
use App\Models\PkpaFinalGradeRelease;
use App\Models\PkpaFinalGradeResult;
use App\Models\PkpaGraduationDecision;
use App\Models\PkpaRotationGradeResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaFinalGradeService
{
    public function __construct(private readonly PkpaAuditService $audit, private readonly PkpaFinalNotificationService $notifications)
    {
    }

    public function calculate(PkpaEnrollment $enrollment, ?User $actor): PkpaFinalGradeCalculation
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin/Koordinator yang dapat menghitung nilai akhir.']);
        }
        $scheme = PkpaFinalAssessmentScheme::with('components')->where('pkpa_program_id', $enrollment->pkpa_program_id)->where('is_current', true)->where('status', 'active')->firstOrFail();
        $blocking = [];
        $componentResults = [];
        $scaledTotal = 0;
        foreach ($scheme->components->where('status', 'active') as $component) {
            $source = null;
            if ($component->component_type === 'wahana_grade') {
                $source = PkpaRotationGradeResult::where('pkpa_enrollment_id', $enrollment->id)
                    ->where('practice_domain_id', $component->source_practice_domain_id)
                    ->whereIn('result_status', ['finalized', 'released'])
                    ->latest()
                    ->first();
                if (! $source && ! $component->allow_missing) {
                    $blocking[] = 'Nilai wahana '.$component->name.' belum finalized.';
                    continue;
                }
                $raw = $source?->final_score ?? '0';
            } else {
                $raw = '0';
                if ($component->is_required && ! $component->allow_missing) {
                    $blocking[] = 'Komponen program '.$component->name.' belum lengkap.';
                    continue;
                }
            }
            $weighted = intdiv($this->scaled($raw) * $this->scaled($component->weight_percentage), 1000000);
            $scaledTotal += $weighted;
            $componentResults[] = [
                'code' => $component->code,
                'name' => $component->name,
                'weight' => $component->weight_percentage,
                'source_type' => $component->component_type,
                'source_id' => $source?->id,
                'raw_score' => $raw,
                'weighted_score' => $this->unscaled($weighted),
            ];
        }
        $calculation = PkpaFinalGradeCalculation::create([
            'pkpa_enrollment_id' => $enrollment->id,
            'pkpa_final_assessment_scheme_id' => $scheme->id,
            'calculation_number' => ((int) PkpaFinalGradeCalculation::where('pkpa_enrollment_id', $enrollment->id)->max('calculation_number')) + 1,
            'status' => empty($blocking) ? 'calculated' : 'blocked',
            'source_snapshot' => ['scheme' => $scheme->only(['id', 'code', 'version_number'])],
            'component_results' => $componentResults,
            'raw_total_score' => empty($blocking) ? $this->unscaled($scaledTotal) : null,
            'final_score' => empty($blocking) ? $this->round($scaledTotal, $scheme->rounding_precision, $scheme->rounding_mode) : null,
            'rounding_snapshot' => ['precision' => $scheme->rounding_precision, 'mode' => $scheme->rounding_mode],
            'blocking_issues' => $blocking,
            'warning_issues' => [],
            'calculated_by_core_user_id' => $actor?->core_user_id,
            'calculated_at' => now(),
        ]);
        $this->audit->record($actor, 'pkpa_final_grade_calculated', $calculation, null, ['status' => $calculation->status]);
        $this->notifications->notifyEnrollment($enrollment, empty($blocking) ? 'final_grade_calculated' : 'final_grade_blocked', $calculation);

        return $calculation;
    }

    public function finalize(PkpaFinalGradeCalculation $calculation, ?User $actor): PkpaFinalGradeResult
    {
        if (! $actor?->hasRole('koordinator_kp') || $calculation->status !== 'calculated') {
            throw ValidationException::withMessages(['authorization' => 'Finalisasi nilai akhir hanya oleh Koordinator dan calculation valid.']);
        }
        return DB::transaction(function () use ($calculation, $actor) {
            if (PkpaFinalGradeResult::where('pkpa_final_grade_calculation_id', $calculation->id)->exists()) {
                return PkpaFinalGradeResult::where('pkpa_final_grade_calculation_id', $calculation->id)->firstOrFail();
            }
            $scheme = PkpaFinalAssessmentScheme::findOrFail($calculation->pkpa_final_assessment_scheme_id);
            $result = PkpaFinalGradeResult::create([
                'pkpa_enrollment_id' => $calculation->pkpa_enrollment_id,
                'pkpa_final_grade_calculation_id' => $calculation->id,
                'pkpa_final_assessment_scheme_id' => $scheme->id,
                'raw_total_score' => $calculation->raw_total_score,
                'final_score' => $calculation->final_score,
                'maximum_score' => $scheme->maximum_score,
                'minimum_passing_score_snapshot' => $scheme->minimum_passing_score,
                'result_status' => 'finalized',
                'source_snapshot' => $calculation->source_snapshot,
                'calculation_snapshot' => $calculation->component_results,
                'finalized_at' => now(),
                'finalized_by_core_user_id' => $actor->core_user_id,
            ]);
            $this->audit->record($actor, 'pkpa_final_grade_finalized', $result);

            return $result;
        });
    }

    public function decide(PkpaEnrollment $enrollment, PkpaFinalGradeResult $result, string $decision, string $reason, ?User $actor): PkpaGraduationDecision
    {
        if (! $actor?->hasRole('koordinator_kp') || ! in_array($decision, ['passed', 'not_passed', 'pending_remedial'], true)) {
            throw ValidationException::withMessages(['authorization' => 'Keputusan kelulusan hanya oleh Koordinator.']);
        }
        $requirementsComplete = $enrollment->requirements()->where('status', '!=', 'completed')->count() === 0;
        if (! $requirementsComplete && $decision === 'passed') {
            throw ValidationException::withMessages(['decision' => 'Seluruh requirement harus completed sebelum passed.']);
        }
        $record = PkpaGraduationDecision::create([
            'pkpa_enrollment_id' => $enrollment->id,
            'pkpa_final_grade_result_id' => $result->id,
            'decision_number' => ((int) PkpaGraduationDecision::where('pkpa_enrollment_id', $enrollment->id)->max('decision_number')) + 1,
            'decision_status' => 'decided',
            'decision' => $decision,
            'readiness_snapshot' => ['requirements_complete' => $requirementsComplete, 'final_score' => $result->final_score],
            'reason' => $reason,
            'decided_at' => now(),
            'decided_by_core_user_id' => $actor->core_user_id,
        ]);
        if ($decision === 'passed') {
            $from = $enrollment->status;
            $enrollment->update(['status' => 'completed']);
            DB::table('pkpa_enrollment_status_histories')->insert(['pkpa_enrollment_id' => $enrollment->id, 'from_status' => $from, 'to_status' => 'completed', 'reason' => $reason, 'changed_by_core_user_id' => $actor->core_user_id, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->audit->record($actor, 'pkpa_graduation_decided', $record, null, ['decision' => $decision]);

        return $record;
    }

    public function release(PkpaFinalGradeResult $result, ?PkpaGraduationDecision $decision, ?User $actor): PkpaFinalGradeRelease
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Release hasil akhir hanya oleh Koordinator.']);
        }
        if (PkpaFinalGradeRelease::where('pkpa_final_grade_result_id', $result->id)->where('status', 'released')->exists()) {
            return PkpaFinalGradeRelease::where('pkpa_final_grade_result_id', $result->id)->where('status', 'released')->firstOrFail();
        }
        $release = PkpaFinalGradeRelease::create([
            'pkpa_final_grade_result_id' => $result->id,
            'pkpa_graduation_decision_id' => $decision?->id,
            'pkpa_enrollment_id' => $result->pkpa_enrollment_id,
            'release_number' => ((int) PkpaFinalGradeRelease::where('pkpa_enrollment_id', $result->pkpa_enrollment_id)->max('release_number')) + 1,
            'status' => 'released',
            'released_at' => now(),
            'released_by_core_user_id' => $actor->core_user_id,
            'student_visible_snapshot' => ['final_score' => $result->final_score, 'maximum_score' => $result->maximum_score, 'decision' => $decision?->decision, 'label' => 'Hasil akademik Program PKPA dalam MY PKPA, bukan dokumen resmi universitas.'],
        ]);
        $result->update(['result_status' => 'released', 'released_at' => now(), 'released_by_core_user_id' => $actor->core_user_id]);
        $this->notifications->notifyEnrollment($result->enrollment, 'final_result_released', $release);

        return $release;
    }

    private function scaled(string|int|float|null $value): int
    {
        return (int) round(((float) ($value ?? 0)) * 10000);
    }

    private function unscaled(int $value): string
    {
        return number_format($value / 10000, 4, '.', '');
    }

    private function round(int $scaled, int $precision, string $mode): string
    {
        $factor = 10 ** max(0, 4 - $precision);
        $base = intdiv($scaled, $factor) * $factor;
        $rem = abs($scaled - $base);
        $rounded = match ($mode) {
            'floor' => $base,
            'ceil' => $rem > 0 ? $base + $factor : $base,
            default => $rem * 2 >= $factor ? $base + $factor : $base,
        };
        return $this->unscaled($rounded);
    }
}

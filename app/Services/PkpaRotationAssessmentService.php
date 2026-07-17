<?php

namespace App\Services;

use App\Models\PkpaAssessmentModeration;
use App\Models\PkpaGradeChangeRequest;
use App\Models\PkpaGradeRelease;
use App\Models\PkpaRotationAssessment;
use App\Models\PkpaRotationAssessmentAssessor;
use App\Models\PkpaRotationComponentScore;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\Concerns\AuthorizesPkpaRotationActors;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRotationAssessmentService
{
    use AuthorizesPkpaRotationActors;

    public function __construct(
        private readonly PkpaAuditService $audit,
        private readonly PkpaAssessmentCalculationService $calculator,
        private readonly PkpaAssessmentNotificationService $notifications
    ) {
    }

    public function createFromRun(PkpaRotationRun $run, ?User $actor): PkpaRotationAssessment
    {
        if (! $this->isCoordinator($actor)) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat membuat assessment.']);
        }
        $run->loadMissing(['requirement.programDomain.activeAssessmentScheme.components', 'supervisorHistories', 'academicReadinessReviews']);
        $scheme = $run->requirement?->programDomain?->activeAssessmentScheme;
        if (! $scheme) {
            throw ValidationException::withMessages(['scheme' => 'Skema penilaian aktif belum tersedia untuk wahana ini.']);
        }
        $latestReadiness = $run->academicReadinessReviews->sortByDesc('reviewed_at')->first();
        if ($scheme->require_academic_readiness && $latestReadiness?->status !== 'ready_for_assessment') {
            throw ValidationException::withMessages(['academic_readiness' => 'Academic readiness harus ready_for_assessment sebelum assessment dibuat.']);
        }

        return DB::transaction(function () use ($run, $scheme, $actor) {
            $assessment = PkpaRotationAssessment::firstOrCreate(
                ['pkpa_rotation_run_id' => $run->id],
                [
                    'source_assessment_scheme_id' => $scheme->id,
                    'scheme_code_snapshot' => $scheme->code,
                    'scheme_name_snapshot' => $scheme->name,
                    'scheme_version_snapshot' => $scheme->version_number,
                    'maximum_score_snapshot' => $scheme->maximum_score,
                    'minimum_passing_score_snapshot' => $scheme->minimum_passing_score,
                    'rounding_precision_snapshot' => $scheme->rounding_precision,
                    'rounding_mode_snapshot' => $scheme->rounding_mode,
                    'status' => 'in_progress',
                    'completion_status' => 'incomplete',
                    'moderation_status' => config('my_pspa.assessment_moderation_enabled') ? 'pending' : 'not_required',
                    'started_at' => now(),
                    'created_by_core_user_id' => $actor?->core_user_id,
                    'updated_by_core_user_id' => $actor?->core_user_id,
                ]
            );

            foreach ($scheme->components()->where('status', 'active')->get() as $component) {
                foreach ($this->assessorPayloads($run, $component->assessor_type) as $assessor) {
                    $assignment = PkpaRotationAssessmentAssessor::firstOrCreate(
                        [
                            'pkpa_rotation_assessment_id' => $assessment->id,
                            'pkpa_assessment_component_id' => $component->id,
                            'assessor_type' => $assessor['assessor_type'],
                            'core_user_id' => $assessor['core_user_id'],
                        ],
                        array_merge($assessor, [
                            'status' => 'assigned',
                            'assigned_at' => now(),
                            'created_by_core_user_id' => $actor?->core_user_id,
                            'updated_by_core_user_id' => $actor?->core_user_id,
                        ])
                    );
                    PkpaRotationComponentScore::firstOrCreate(
                        [
                            'pkpa_rotation_assessment_id' => $assessment->id,
                            'pkpa_assessment_component_id' => $component->id,
                            'assessor_assignment_id' => $assignment->id,
                        ],
                        [
                            'component_code_snapshot' => $component->code,
                            'component_name_snapshot' => $component->name,
                            'component_type_snapshot' => $component->component_type,
                            'weight_percentage_snapshot' => $component->weight_percentage,
                            'calculation_method_snapshot' => $component->calculation_method,
                            'status' => 'not_started',
                            'created_by_core_user_id' => $actor?->core_user_id,
                            'updated_by_core_user_id' => $actor?->core_user_id,
                        ]
                    );
                }
            }
            $this->audit->record($actor, 'pkpa_rotation_assessment_created', $assessment);
            $this->notifications->notifyAssessment($assessment, 'assessment_assigned', $assessment, ['field_supervisor', 'internal_supervisor']);

            return $assessment->refresh();
        });
    }

    public function saveDirectScore(PkpaRotationComponentScore $score, string $rawScore, ?string $comments, ?User $actor): PkpaRotationComponentScore
    {
        $score->loadMissing(['assessment.rotationRun.supervisorHistories', 'component', 'assessor']);
        $this->ensureCanScore($score, $actor);
        if (in_array($score->status, ['submitted', 'approved', 'locked'], true)) {
            throw ValidationException::withMessages(['score' => 'Nilai submitted atau locked tidak dapat diedit langsung.']);
        }
        $component = $score->component;
        if ((float) $rawScore < 0 || (float) $rawScore > (float) $component->maximum_raw_score) {
            throw ValidationException::withMessages(['raw_score' => 'Nilai berada di luar rentang komponen.']);
        }

        $calculated = $this->calculator->calculateComponent($score, $rawScore, $component->maximum_raw_score, $score->assessment->maximum_score_snapshot);
        $score->update(array_merge($calculated, [
            'status' => 'draft',
            'comments' => $comments,
            'calculated_at' => now(),
            'row_version' => $score->row_version + 1,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]));
        $score->assessor?->update(['status' => 'in_progress']);
        $this->audit->record($actor, 'pkpa_assessment_score_draft_saved', $score, null, ['component' => $score->component_code_snapshot]);

        return $score->refresh();
    }

    public function submitScore(PkpaRotationComponentScore $score, ?User $actor): PkpaRotationComponentScore
    {
        $score->loadMissing(['assessment.rotationRun.supervisorHistories', 'component', 'assessor']);
        $this->ensureCanScore($score, $actor);
        if (is_null($score->raw_score) || is_null($score->weighted_score)) {
            throw ValidationException::withMessages(['score' => 'Nilai belum lengkap.']);
        }
        if (in_array($score->status, ['submitted', 'approved', 'locked'], true)) {
            return $score;
        }

        $score->update(['status' => 'submitted', 'submitted_at' => now(), 'locked_at' => now(), 'row_version' => $score->row_version + 1]);
        $score->assessor?->update(['status' => 'submitted', 'submitted_at' => now(), 'locked_at' => now()]);
        $assessment = $this->refreshCompletion($score->assessment()->firstOrFail(), $actor);
        $this->audit->record($actor, 'pkpa_assessment_score_submitted', $score);
        $this->notifications->notifyAssessment($assessment, 'assessment_submitted', $score, ['student']);
        if ($assessment->completion_status === 'complete') {
            $this->notifications->notifyAssessment($assessment, 'all_assessors_submitted', $assessment, ['student', 'internal_supervisor', 'field_supervisor']);
        }

        return $score->refresh();
    }

    public function refreshCompletion(PkpaRotationAssessment $assessment, ?User $actor = null): PkpaRotationAssessment
    {
        $assessment->loadMissing(['componentScores.component', 'assessors', 'rotationRun.academicReadinessReviews']);
        $latestReadiness = $assessment->rotationRun->academicReadinessReviews->sortByDesc('reviewed_at')->first();
        $requiredScores = $assessment->componentScores->filter(fn ($score) => $score->component?->is_required);
        $missing = $requiredScores->filter(fn ($score) => ! in_array($score->status, ['submitted', 'approved', 'locked'], true))->count();
        $status = 'incomplete';
        if ($latestReadiness?->status !== 'ready_for_assessment') {
            $status = 'blocked';
        } elseif ($missing === 0 && $requiredScores->isNotEmpty()) {
            $status = 'complete';
        } elseif ($assessment->componentScores->whereIn('status', ['draft', 'submitted'])->isNotEmpty()) {
            $status = 'partially_complete';
        }
        $assessment->update(['completion_status' => $status, 'updated_by_core_user_id' => $actor?->core_user_id]);

        return $assessment->refresh();
    }

    public function moderate(PkpaRotationAssessment $assessment, array $data, ?User $actor): PkpaAssessmentModeration
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Moderasi hanya oleh Koordinator dan alasan wajib.']);
        }
        $total = $this->calculator->total($assessment);
        $final = $data['final_total_score'] ?? $total['final_score'];
        if ((float) $final < 0 || (float) $final > (float) $assessment->maximum_score_snapshot) {
            throw ValidationException::withMessages(['final_total_score' => 'Nilai moderasi di luar rentang.']);
        }
        $moderation = PkpaAssessmentModeration::create([
            'pkpa_rotation_assessment_id' => $assessment->id,
            'status' => 'completed',
            'moderation_type' => $data['moderation_type'] ?? 'consistency_review',
            'reason' => $data['reason'],
            'original_total_score' => $total['final_score'],
            'proposed_total_score' => $final,
            'final_total_score' => $final,
            'component_adjustments' => $data['component_adjustments'] ?? [],
            'review_notes' => $data['review_notes'] ?? null,
            'requested_by_core_user_id' => $actor->core_user_id,
            'moderated_by_core_user_id' => $actor->core_user_id,
            'approved_by_core_user_id' => $actor->core_user_id,
            'requested_at' => now(),
            'moderated_at' => now(),
            'approved_at' => now(),
        ]);
        $assessment->update(['moderation_status' => 'completed', 'moderated_at' => now(), 'status' => 'under_moderation']);
        $this->audit->record($actor, 'pkpa_assessment_moderation_completed', $moderation, null, ['final' => $final]);
        $this->notifications->notifyAssessment($assessment, 'moderation_completed', $moderation, ['field_supervisor', 'internal_supervisor']);

        return $moderation;
    }

    public function finalize(PkpaRotationAssessment $assessment, ?User $actor): PkpaRotationGradeResult
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Finalisasi nilai hanya oleh Koordinator PKPA.']);
        }
        $assessment = $this->refreshCompletion($assessment, $actor);
        if ($assessment->completion_status !== 'complete') {
            throw ValidationException::withMessages(['completion' => 'Assessment belum lengkap untuk finalisasi.']);
        }
        if ($assessment->gradeResult()->whereIn('result_status', ['finalized', 'released'])->exists()) {
            return $assessment->gradeResult()->whereIn('result_status', ['finalized', 'released'])->firstOrFail();
        }

        return DB::transaction(function () use ($assessment, $actor) {
            $assessment->loadMissing(['componentScores', 'rotationRun']);
            $total = $this->calculator->total($assessment);
            $moderated = PkpaAssessmentModeration::where('pkpa_rotation_assessment_id', $assessment->id)->where('status', 'completed')->latest()->first();
            $finalScore = $moderated?->final_total_score ?? $total['final_score'];
            $run = $assessment->rotationRun;
            $result = PkpaRotationGradeResult::create([
                'pkpa_rotation_assessment_id' => $assessment->id,
                'pkpa_rotation_run_id' => $run->id,
                'pkpa_enrollment_id' => $run->pkpa_enrollment_id,
                'pkpa_enrollment_requirement_id' => $run->pkpa_enrollment_requirement_id,
                'practice_domain_id' => $run->practice_domain_id,
                'assessment_scheme_id' => $assessment->source_assessment_scheme_id,
                'raw_total_score' => $total['raw_total_score'],
                'moderated_total_score' => $moderated?->final_total_score,
                'final_score' => $finalScore,
                'maximum_score' => $assessment->maximum_score_snapshot,
                'minimum_passing_score_snapshot' => $assessment->minimum_passing_score_snapshot,
                'result_status' => 'finalized',
                'calculation_snapshot' => $total,
                'component_snapshot' => $assessment->componentScores->map(fn ($score) => [
                    'component' => $score->component_code_snapshot,
                    'raw' => $score->raw_score,
                    'normalized' => $score->normalized_score,
                    'weighted' => $score->weighted_score,
                    'status' => $score->status,
                ])->values()->all(),
                'finalized_at' => now(),
                'finalized_by_core_user_id' => $actor->core_user_id,
            ]);
            $assessment->componentScores()->update(['status' => 'locked', 'locked_at' => now()]);
            $assessment->assessors()->update(['status' => 'locked', 'locked_at' => now()]);
            $assessment->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'locked_at' => now(),
                'finalized_by_core_user_id' => $actor->core_user_id,
                'row_version' => $assessment->row_version + 1,
            ]);
            $this->audit->record($actor, 'pkpa_grade_finalized', $result, null, ['final_score' => $finalScore]);
            $this->notifications->notifyAssessment($assessment, 'grade_finalized', $result, ['field_supervisor', 'internal_supervisor']);

            return $result;
        });
    }

    public function release(PkpaRotationGradeResult $result, ?User $actor): PkpaGradeRelease
    {
        if (! config('my_pspa.grade_release_enabled') || ! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Release nilai hanya oleh Koordinator PKPA.']);
        }
        if ($result->releases()->where('status', 'released')->exists()) {
            return $result->releases()->where('status', 'released')->firstOrFail();
        }
        $result->loadMissing('assessment.rotationRun.practiceDomain', 'rotationRun.practiceSite');
        $release = PkpaGradeRelease::create([
            'pkpa_rotation_grade_result_id' => $result->id,
            'pkpa_rotation_assessment_id' => $result->pkpa_rotation_assessment_id,
            'release_number' => ((int) PkpaGradeRelease::where('pkpa_rotation_assessment_id', $result->pkpa_rotation_assessment_id)->max('release_number')) + 1,
            'status' => 'released',
            'released_at' => now(),
            'released_by_core_user_id' => $actor->core_user_id,
            'student_visible_snapshot' => [
                'label' => 'Nilai wahana PKPA, bukan nilai akhir keseluruhan Program PKPA.',
                'wahana' => $result->rotationRun?->practiceDomain?->name,
                'tempat' => $result->rotationRun?->practiceSite?->name,
                'final_score' => $result->final_score,
                'maximum_score' => $result->maximum_score,
            ],
        ]);
        $result->update(['result_status' => 'released', 'released_at' => now(), 'released_by_core_user_id' => $actor->core_user_id]);
        $result->assessment()->update(['grade_release_status' => 'released', 'released_at' => now()]);
        $this->audit->record($actor, 'pkpa_grade_released', $release);
        $this->notifications->notifyAssessment($result->assessment, 'grade_released', $release, ['student']);

        return $release;
    }

    public function withdrawRelease(PkpaGradeRelease $release, string $reason, ?User $actor): PkpaGradeRelease
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Penarikan release wajib oleh Koordinator dengan alasan.']);
        }
        $release->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdrawn_by_core_user_id' => $actor->core_user_id, 'withdrawal_reason' => $reason]);
        $release->assessment()->update(['grade_release_status' => 'withdrawn']);
        $this->audit->record($actor, 'pkpa_grade_withdrawn', $release, null, ['reason' => $reason]);
        $this->notifications->notifyAssessment($release->assessment, 'grade_release_withdrawn', $release, ['student']);

        return $release->refresh();
    }

    public function createGradeChange(PkpaRotationAssessment $assessment, array $data, ?User $actor): PkpaGradeChangeRequest
    {
        if (! $this->isCoordinator($actor) || blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Permintaan perubahan nilai wajib memiliki alasan.']);
        }
        $request = PkpaGradeChangeRequest::create([
            'pkpa_rotation_assessment_id' => $assessment->id,
            'request_number' => 'GCR-'.$assessment->id.'-'.now()->format('YmdHis'),
            'request_type' => $data['request_type'] ?? 'calculation_correction',
            'status' => 'submitted',
            'reason' => $data['reason'],
            'impact_summary' => $data['impact_summary'] ?? null,
            'requested_by_core_user_id' => $actor?->core_user_id,
            'requested_at' => now(),
        ]);
        $this->audit->record($actor, 'pkpa_grade_change_requested', $request);

        return $request;
    }

    private function ensureCanScore(PkpaRotationComponentScore $score, ?User $actor): void
    {
        if (! $actor || $score->assessor?->core_user_id !== $actor->core_user_id || $score->assessor?->status === 'replaced') {
            throw ValidationException::withMessages(['authorization' => 'Anda tidak berwenang mengisi nilai komponen ini.']);
        }
    }

    private function assessorPayloads(PkpaRotationRun $run, string $assessorType): array
    {
        if ($assessorType === 'system') {
            return [['assessor_type' => 'system', 'core_user_id' => 'system', 'name_snapshot' => 'Sistem', 'role_snapshot' => 'system']];
        }
        $types = match ($assessorType) {
            'field_supervisor' => ['field'],
            'internal_supervisor' => ['internal'],
            'multiple' => ['field', 'internal'],
            'coordinator' => ['coordinator'],
            default => [$assessorType],
        };

        return collect($types)->flatMap(function (string $type) use ($run) {
            if ($type === 'coordinator') {
                return [['assessor_type' => 'coordinator', 'core_user_id' => null, 'name_snapshot' => 'Koordinator PKPA', 'role_snapshot' => 'koordinator_kp']];
            }
            return $run->supervisorHistories
                ->where('supervisor_type', $type)
                ->where('status', 'active')
                ->map(fn ($supervisor) => [
                    'assessor_type' => $type === 'field' ? 'field_supervisor' : 'internal_supervisor',
                    'core_user_id' => $supervisor->core_user_id,
                    'name_snapshot' => $supervisor->name_snapshot,
                    'role_snapshot' => $supervisor->role_snapshot,
                    'source_rotation_supervisor_history_id' => $supervisor->id,
                ]);
        })->values()->all();
    }
}

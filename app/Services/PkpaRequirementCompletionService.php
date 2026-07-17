<?php

namespace App\Services;

use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaEnrollmentRequirementCompletion;
use App\Models\PkpaRemedialCase;
use App\Models\PkpaRotationGradeResult;
use App\Models\PkpaRotationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaRequirementCompletionService
{
    public function __construct(private readonly PkpaAuditService $audit, private readonly PkpaFinalNotificationService $notifications)
    {
    }

    public function evaluate(PkpaEnrollmentRequirement $requirement, ?User $actor): PkpaEnrollmentRequirementCompletion
    {
        $requirement->loadMissing('enrollment');
        $run = PkpaRotationRun::where('pkpa_enrollment_requirement_id', $requirement->id)->latest()->first();
        $grade = $run ? PkpaRotationGradeResult::where('pkpa_rotation_run_id', $run->id)->whereIn('result_status', ['finalized', 'released'])->latest()->first() : null;
        $readiness = $run?->academicReadinessReviews()->latest('reviewed_at')->first();
        $issues = [];
        if (! $run || $run->status !== 'operational_complete') {
            $issues[] = 'Operasional rotasi belum selesai.';
        }
        if ($readiness?->status !== 'ready_for_assessment') {
            $issues[] = 'Academic readiness belum siap.';
        }
        if (! $grade) {
            $issues[] = 'Nilai wahana belum finalized.';
        }
        if ($grade && $grade->minimum_passing_score_snapshot !== null && (float) $grade->final_score < (float) $grade->minimum_passing_score_snapshot) {
            $issues[] = 'Nilai wahana belum memenuhi minimum.';
        }
        if (PkpaRemedialCase::where('pkpa_enrollment_requirement_id', $requirement->id)->whereIn('status', ['submitted', 'approved', 'in_progress'])->exists()) {
            $issues[] = 'Masih ada remedial aktif.';
        }
        $status = empty($issues) ? 'eligible' : 'pending';
        $completion = PkpaEnrollmentRequirementCompletion::updateOrCreate(
            ['pkpa_enrollment_requirement_id' => $requirement->id, 'status' => 'eligible'],
            [
                'pkpa_enrollment_id' => $requirement->pkpa_enrollment_id,
                'practice_domain_id' => $requirement->practice_domain_id,
                'selected_practice_domain_option_id' => $requirement->selected_practice_domain_option_id,
                'rotation_run_id' => $run?->id,
                'rotation_grade_result_id' => $grade?->id,
                'status' => $status,
                'operational_complete_snapshot' => ['run_id' => $run?->id, 'status' => $run?->status],
                'academic_readiness_snapshot' => ['status' => $readiness?->status, 'id' => $readiness?->id],
                'grade_snapshot' => ['grade_result_id' => $grade?->id, 'final_score' => $grade?->final_score, 'issues' => $issues],
            ]
        );
        $this->audit->record($actor, 'pkpa_requirement_completion_evaluated', $completion, null, ['status' => $status]);
        if ($status === 'eligible') {
            $this->notifications->notifyEnrollment($requirement->enrollment, 'requirement_eligible_for_completion', $completion);
        }

        return $completion;
    }

    public function complete(PkpaEnrollmentRequirement $requirement, string $reason, ?User $actor): PkpaEnrollmentRequirementCompletion
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Completion requirement wajib oleh Koordinator dengan alasan.']);
        }
        return DB::transaction(function () use ($requirement, $reason, $actor) {
            $completion = $this->evaluate($requirement, $actor);
            if ($completion->status !== 'eligible') {
                throw ValidationException::withMessages(['completion' => 'Requirement belum eligible untuk completed.']);
            }
            $completion->update(['status' => 'completed', 'completed_at' => now(), 'completion_reason' => $reason, 'completed_by_core_user_id' => $actor->core_user_id]);
            $requirement->update(['status' => 'completed']);
            $this->audit->record($actor, 'pkpa_requirement_completed', $completion);
            $this->notifications->notifyEnrollment($requirement->enrollment, 'requirement_completed', $completion);

            return $completion->refresh();
        });
    }

    public function reopen(PkpaEnrollmentRequirement $requirement, string $reason, ?User $actor): PkpaEnrollmentRequirementCompletion
    {
        if (! $actor?->hasRole('koordinator_kp') || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Reopen requirement wajib oleh Koordinator dengan alasan.']);
        }
        $completion = $requirement->completions()->latest()->firstOrFail();
        $completion->update(['status' => 'reopened', 'reopened_at' => now(), 'reopen_reason' => $reason, 'reopened_by_core_user_id' => $actor->core_user_id]);
        $requirement->update(['status' => 'active']);
        $this->audit->record($actor, 'pkpa_requirement_reopened', $completion);

        return $completion->refresh();
    }
}

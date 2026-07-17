<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaRemedialAttempt;
use App\Models\PkpaRemedialCase;
use App\Models\PkpaRemedialPolicy;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PkpaRemedialService
{
    public function __construct(private readonly PkpaAuditService $audit, private readonly PkpaFinalNotificationService $notifications)
    {
    }

    public function savePolicy($program, array $data, ?User $actor): PkpaRemedialPolicy
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin/Koordinator yang dapat membuat policy remedial.']);
        }
        return tap(PkpaRemedialPolicy::updateOrCreate(
            ['pkpa_program_id' => $program->id, 'code' => $data['code']],
            array_merge($data, ['created_by_core_user_id' => $actor?->core_user_id, 'updated_by_core_user_id' => $actor?->core_user_id])
        ), fn ($policy) => $this->audit->record($actor, 'pkpa_remedial_policy_saved', $policy));
    }

    public function openCase(PkpaEnrollment $enrollment, array $data, ?User $actor): PkpaRemedialCase
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp']) || blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Kasus remedial wajib memiliki alasan.']);
        }
        $policy = isset($data['pkpa_remedial_policy_id']) ? PkpaRemedialPolicy::find($data['pkpa_remedial_policy_id']) : null;
        $case = PkpaRemedialCase::create([
            'pkpa_enrollment_id' => $enrollment->id,
            'pkpa_enrollment_requirement_id' => $data['pkpa_enrollment_requirement_id'] ?? null,
            'pkpa_rotation_grade_result_id' => $data['pkpa_rotation_grade_result_id'] ?? null,
            'pkpa_program_assessment_id' => $data['pkpa_program_assessment_id'] ?? null,
            'pkpa_remedial_policy_id' => $policy?->id,
            'case_type' => $data['case_type'] ?? 'custom',
            'status' => 'submitted',
            'reason' => $data['reason'],
            'policy_snapshot' => $policy?->only(['id', 'code', 'score_replacement_policy', 'maximum_attempts']),
            'opened_by_core_user_id' => $actor?->core_user_id,
            'opened_at' => now(),
        ]);
        $this->audit->record($actor, 'pkpa_remedial_case_opened', $case);
        $this->notifications->notifyEnrollment($enrollment, 'remedial_case_opened', $case);

        return $case;
    }

    public function approve(PkpaRemedialCase $case, ?User $actor): PkpaRemedialCase
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Approval remedial hanya oleh Koordinator.']);
        }
        $case->update(['status' => 'approved', 'approved_at' => now(), 'approved_by_core_user_id' => $actor->core_user_id]);
        $this->audit->record($actor, 'pkpa_remedial_approved', $case);

        return $case->refresh();
    }

    public function recordAttempt(PkpaRemedialCase $case, array $data, ?User $actor): PkpaRemedialAttempt
    {
        if ($case->status !== 'approved') {
            throw ValidationException::withMessages(['case' => 'Kasus remedial harus approved sebelum attempt.']);
        }
        $max = $case->policy_snapshot['maximum_attempts'] ?? null;
        if ($max && $case->attempts()->count() >= $max) {
            throw ValidationException::withMessages(['attempt' => 'Attempt remedial melebihi batas policy.']);
        }
        $attempt = PkpaRemedialAttempt::create(array_merge($data, [
            'pkpa_remedial_case_id' => $case->id,
            'attempt_number' => ((int) $case->attempts()->max('attempt_number')) + 1,
            'status' => $data['status'] ?? 'completed',
            'completed_at' => now(),
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]));
        $case->update(['status' => 'completed', 'closed_at' => now(), 'closed_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'pkpa_remedial_attempt_completed', $attempt);

        return $attempt;
    }
}

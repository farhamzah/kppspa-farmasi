<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaProgram;
use App\Models\PkpaStudentGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaEnrollmentService
{
    public function __construct(
        private readonly PkpaCoreStudentResolver $resolver,
        private readonly PkpaEnrollmentRequirementService $requirementService,
        private readonly PkpaStudentGroupService $groupService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function create(PkpaProgram $program, array $criteria, ?PkpaStudentGroup $group, ?User $actor): PkpaEnrollment
    {
        if (in_array($program->status, ['completed', 'archived'], true)) {
            throw ValidationException::withMessages(['pkpa_program_id' => 'Program tidak menerima peserta baru.']);
        }

        $resolved = $this->resolver->resolve($criteria);
        if (! ($resolved['ok'] ?? false)) {
            throw ValidationException::withMessages(['core_user_id' => $resolved['message'] ?? 'Data mahasiswa Core tidak valid.']);
        }

        $student = $resolved['student'];
        if (PkpaEnrollment::withTrashed()->where('pkpa_program_id', $program->id)->where('core_user_id', $student['core_user_id'])->exists()) {
            throw ValidationException::withMessages(['core_user_id' => 'Mahasiswa sudah terdaftar pada program ini.']);
        }

        return DB::transaction(function () use ($program, $student, $group, $actor, $criteria) {
            $enrollment = PkpaEnrollment::create([
                'pkpa_program_id' => $program->id,
                'core_user_id' => $student['core_user_id'],
                'student_number' => $student['student_number'],
                'student_name_snapshot' => $student['name'],
                'student_email_snapshot' => $student['email'],
                'study_program_snapshot' => $student['study_program'],
                'cohort_snapshot' => $student['cohort'],
                'academic_status_snapshot' => $student['academic_status'],
                'core_account_status_snapshot' => $student['account_status'],
                'status' => 'active',
                'enrolled_at' => now(),
                'activated_at' => now(),
                'notes' => $criteria['notes'] ?? null,
                'last_core_synced_at' => now(),
                'last_core_sync_status' => 'ok',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);

            $this->requirementService->ensureRequirements($enrollment, $actor);
            if ($group) {
                $this->groupService->addMember($group, $enrollment, $actor);
            }

            $this->audit->record($actor, 'enrollment_created', $enrollment, null, $enrollment->only(['pkpa_program_id', 'core_user_id', 'student_number', 'status']));

            return $enrollment->load(['requirements.practiceDomain', 'activeGroupMembership.group']);
        });
    }

    /**
     * @return array{created: Collection<int, PkpaEnrollment>, errors: array<string, string>}
     */
    public function createMany(PkpaProgram $program, array $coreUserIds, ?PkpaStudentGroup $group, ?User $actor, array $sharedCriteria = []): array
    {
        $created = collect();
        $errors = [];

        foreach (collect($coreUserIds)->filter()->map(fn ($id) => (string) $id)->unique()->values() as $coreUserId) {
            try {
                $created->push($this->create($program, [
                    'core_user_id' => $coreUserId,
                    'notes' => $sharedCriteria['notes'] ?? null,
                ], $group, $actor));
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? 'Data mahasiswa Core tidak valid.';
                $errors[$coreUserId] = $message;
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
        ];
    }

    public function cancel(PkpaEnrollment $enrollment, string $reason, ?User $actor): PkpaEnrollment
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Alasan pembatalan wajib diisi.']);
        }

        return DB::transaction(function () use ($enrollment, $reason, $actor) {
            $old = $enrollment->only(['status', 'cancelled_at', 'cancellation_reason']);
            $enrollment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'enrollment_cancelled', $enrollment, $old, $enrollment->only(array_keys($old)));

            return $enrollment->refresh();
        });
    }

    public function changeStatus(PkpaEnrollment $enrollment, string $status, ?User $actor): PkpaEnrollment
    {
        if (! in_array($status, ['active', 'on_hold', 'archived'], true)) {
            throw ValidationException::withMessages(['status' => 'Status belum dapat dipilih pada Tahap 02.']);
        }

        $old = $enrollment->only(['status']);
        $enrollment->update(['status' => $status, 'updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'enrollment_status_changed', $enrollment, $old, ['status' => $status]);

        return $enrollment->refresh();
    }
}

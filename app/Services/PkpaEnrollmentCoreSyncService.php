<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PkpaEnrollmentCoreSyncService
{
    public function __construct(
        private readonly PkpaCoreStudentResolver $resolver,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function syncOne(PkpaEnrollment $enrollment, ?User $actor): PkpaEnrollment
    {
        $old = $enrollment->only([
            'student_number',
            'student_name_snapshot',
            'student_email_snapshot',
            'study_program_snapshot',
            'cohort_snapshot',
            'academic_status_snapshot',
            'core_account_status_snapshot',
            'last_core_synced_at',
            'last_core_sync_status',
            'last_core_sync_message',
        ]);

        $resolved = $this->resolver->resolve(['core_user_id' => $enrollment->core_user_id]);

        if (! ($resolved['ok'] ?? false) && ! isset($resolved['student'])) {
            $enrollment->update([
                'last_core_sync_status' => 'failed',
                'last_core_sync_message' => $resolved['message'] ?? 'Core tidak tersedia.',
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'enrollment_core_sync_failed', $enrollment, $old, $enrollment->only(array_keys($old)));

            return $enrollment->refresh();
        }

        $student = $resolved['student'];

        $enrollment->update([
            'student_number' => $student['student_number'],
            'student_name_snapshot' => $student['name'],
            'student_email_snapshot' => $student['email'],
            'study_program_snapshot' => $student['study_program'],
            'cohort_snapshot' => $student['cohort'],
            'academic_status_snapshot' => $student['academic_status'],
            'core_account_status_snapshot' => $student['account_status'],
            'last_core_synced_at' => now(),
            'last_core_sync_status' => ($resolved['ok'] ?? false) ? 'ok' : 'warning',
            'last_core_sync_message' => ($resolved['ok'] ?? false) ? null : ($resolved['message'] ?? 'Perlu ditinjau.'),
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);

        $this->audit->record($actor, 'enrollment_core_synced', $enrollment, $old, $enrollment->only(array_keys($old)));

        return $enrollment->refresh();
    }

    public function syncProgram(PkpaProgram $program, ?User $actor): int
    {
        $count = 0;
        DB::transaction(function () use ($program, $actor, &$count) {
            foreach ($program->enrollments()->get() as $enrollment) {
                $this->syncOne($enrollment, $actor);
                $count++;
            }
        });

        return $count;
    }
}

<?php

namespace App\Services\Integration;

use App\Models\KpAssignment;
use Illuminate\Support\Collection;

class KpTuDocumentPayloadPreviewService
{
    public const DOCUMENTS = [
        'placement_letter' => 'KP_PLACEMENT_LETTER',
        'supervisor_assignment_letter' => 'KP_SUPERVISOR_ASSIGNMENT_LETTER',
        'examiner_assignment_letter' => 'KP_EXAMINER_ASSIGNMENT_LETTER',
        'exam_invitation' => 'KP_EXAM_INVITATION',
        'exam_minutes' => 'KP_EXAM_MINUTES',
        'score_recap' => 'KP_SCORE_RECAP',
        'final_report_archive' => 'KP_FINAL_REPORT_ARCHIVE',
    ];

    public function preview(?int $assignmentId = null, ?string $documentType = null, int $limit = 5): array
    {
        $assignments = $this->assignments($assignmentId, $limit);
        $documents = $assignments
            ->flatMap(fn (KpAssignment $assignment) => $this->documentsFor($assignment, $documentType))
            ->values();

        return [
            'source_app' => 'kp-farmasi',
            'contract_version' => 'kp-tu-doc-v1',
            'dry_run' => true,
            'external_request_sent' => false,
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'assignment_id' => $assignmentId,
                'document_type' => $documentType,
                'limit' => $limit,
            ],
            'summary' => [
                'assignments_scanned' => $assignments->count(),
                'documents_previewed' => $documents->count(),
            ],
            'documents' => $documents->all(),
            'validation_warnings' => $assignments->isEmpty() ? ['No KP assignment found for preview.'] : [],
        ];
    }

    private function assignments(?int $assignmentId, int $limit): Collection
    {
        return KpAssignment::query()
            ->with([
                'student.user',
                'period.documentRequirements',
                'place',
                'internalSupervisor.user',
                'fieldSupervisor.user',
                'exam.examiner.user',
                'exam.supervisor.user',
                'finalScore',
                'finalReport.latestFile',
            ])
            ->when($assignmentId, fn ($query) => $query->whereKey($assignmentId))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 25)))
            ->get();
    }

    private function documentsFor(KpAssignment $assignment, ?string $documentType): Collection
    {
        return collect(self::DOCUMENTS)
            ->when($documentType, fn (Collection $items) => $items->only($documentType))
            ->map(fn (string $serviceCode, string $type) => $this->payload($assignment, $type, $serviceCode))
            ->values();
    }

    private function payload(KpAssignment $assignment, string $documentType, string $serviceCode): array
    {
        $exam = $assignment->exam;
        $finalScore = $assignment->finalScore;
        $finalReport = $assignment->finalReport;

        return [
            'source_app' => 'kp-farmasi',
            'source_module' => $this->sourceModule($documentType),
            'source_reference_id' => $this->referenceId($assignment, $documentType),
            'document_type' => $documentType,
            'service_code' => $serviceCode,
            'dry_run' => true,
            'status' => $this->statusFor($assignment, $documentType),
            'student' => $this->studentSnapshot($assignment),
            'period' => $this->periodSnapshot($assignment),
            'placement' => $this->placementSnapshot($assignment),
            'supervisors' => [
                'internal' => $this->lecturerSnapshot($assignment->internalSupervisor),
                'field' => $this->fieldSupervisorSnapshot($assignment->fieldSupervisor),
            ],
            'examiner' => $this->lecturerSnapshot($exam?->examiner),
            'exam_schedule' => $exam ? [
                'kp_exam_id' => $exam->id,
                'status' => $exam->status,
                'date' => $exam->exam_date?->toDateString(),
                'start_time' => $this->timeValue($exam->start_time),
                'end_time' => $this->timeValue($exam->end_time),
                'mode' => $exam->mode,
                'room' => $exam->room,
                'meeting_link_present' => filled($exam->meeting_link),
            ] : null,
            'grade' => $finalScore ? [
                'kp_final_score_id' => $finalScore->id,
                'status' => $finalScore->status,
                'final_score' => (float) $finalScore->final_score,
                'final_grade' => $finalScore->final_grade,
                'published_at' => $finalScore->published_at?->toIso8601String(),
            ] : null,
            'file_reference' => $finalReport ? [
                'kp_final_report_id' => $finalReport->id,
                'status' => $finalReport->status,
                'current_version' => $finalReport->current_version,
                'latest_file_available' => (bool) $finalReport->latestFile,
                'file_path_exposed' => false,
                'download_owner_app' => 'kp-farmasi',
            ] : [
                'file_path_exposed' => false,
                'download_owner_app' => 'kp-farmasi',
            ],
            'validation_warnings' => $this->warnings($assignment, $documentType),
        ];
    }

    private function sourceModule(string $documentType): string
    {
        return match ($documentType) {
            'examiner_assignment_letter', 'exam_invitation', 'exam_minutes' => 'exam',
            'score_recap' => 'assessment',
            'final_report_archive' => 'final_report',
            default => 'assignment',
        };
    }

    private function referenceId(KpAssignment $assignment, string $documentType): string
    {
        return match ($documentType) {
            'examiner_assignment_letter', 'exam_invitation', 'exam_minutes' => 'kp_exam:'.($assignment->exam?->id ?? 'missing'),
            'score_recap' => 'kp_final_score:'.($assignment->finalScore?->id ?? 'missing'),
            'final_report_archive' => 'kp_final_report:'.($assignment->finalReport?->id ?? 'missing'),
            default => 'kp_assignment:'.$assignment->id,
        };
    }

    private function statusFor(KpAssignment $assignment, string $documentType): string
    {
        return $this->warnings($assignment, $documentType) ? 'blocked_for_generation' : 'ready_for_preview';
    }

    private function studentSnapshot(KpAssignment $assignment): array
    {
        $student = $assignment->student;

        return [
            'kp_student_id' => $student?->id,
            'core_student_id' => $student?->core_student_id,
            'nim' => $student?->nim,
            'name' => $student?->user?->name,
            'study_program' => $student?->study_program,
            'class_name' => $student?->class_name,
        ];
    }

    private function periodSnapshot(KpAssignment $assignment): array
    {
        $period = $assignment->period;

        return [
            'kp_period_id' => $period?->id,
            'name' => $period?->name,
            'academic_year' => $period?->academic_year,
            'semester' => $period?->semester,
            'kp_start_date' => $period?->kp_start_date?->toDateString(),
            'kp_end_date' => $period?->kp_end_date?->toDateString(),
        ];
    }

    private function placementSnapshot(KpAssignment $assignment): array
    {
        return [
            'kp_assignment_id' => $assignment->id,
            'status' => $assignment->status,
            'started_at' => $assignment->started_at?->toDateString(),
            'ended_at' => $assignment->ended_at?->toDateString(),
            'place' => [
                'kp_place_id' => $assignment->place?->id,
                'name' => $assignment->place?->name,
                'type' => $assignment->place?->type,
                'city' => $assignment->place?->city,
                'province' => $assignment->place?->province,
            ],
        ];
    }

    private function lecturerSnapshot($lecturer): ?array
    {
        if (! $lecturer) {
            return null;
        }

        return [
            'kp_lecturer_id' => $lecturer->id,
            'core_lecturer_id' => $lecturer->core_lecturer_id,
            'name' => lecturer_display_name($lecturer),
            'nidn_nip' => $lecturer->nidn_nip,
            'department' => $lecturer->department,
            'study_program' => $lecturer->study_program,
        ];
    }

    private function fieldSupervisorSnapshot($fieldSupervisor): ?array
    {
        if (! $fieldSupervisor) {
            return null;
        }

        return [
            'kp_field_supervisor_id' => $fieldSupervisor->id,
            'core_user_id' => $fieldSupervisor->core_user_id,
            'name' => field_supervisor_display_name($fieldSupervisor),
            'institution_name' => $fieldSupervisor->institution_name,
            'position' => $fieldSupervisor->position,
        ];
    }

    private function warnings(KpAssignment $assignment, string $documentType): array
    {
        $warnings = [];

        if (! $assignment->student) {
            $warnings[] = 'Assignment has no student.';
        }

        if (! $assignment->period) {
            $warnings[] = 'Assignment has no period.';
        }

        if (! $assignment->place) {
            $warnings[] = 'Assignment has no KP place.';
        }

        if (in_array($documentType, ['placement_letter', 'supervisor_assignment_letter'], true) && ! $assignment->internalSupervisor) {
            $warnings[] = 'Internal supervisor is missing.';
        }

        if ($documentType === 'supervisor_assignment_letter' && ! $assignment->fieldSupervisor) {
            $warnings[] = 'Field supervisor is missing.';
        }

        if (in_array($documentType, ['examiner_assignment_letter', 'exam_invitation', 'exam_minutes'], true) && ! $assignment->exam) {
            $warnings[] = 'Exam schedule is missing.';
        }

        if ($documentType === 'exam_minutes' && $assignment->exam?->status !== 'selesai') {
            $warnings[] = 'Exam is not completed yet.';
        }

        if ($documentType === 'score_recap' && ! $assignment->finalScore) {
            $warnings[] = 'Final score is missing.';
        }

        if ($documentType === 'final_report_archive' && ! $assignment->finalReport?->isApproved()) {
            $warnings[] = 'Final report is not approved yet.';
        }

        return $warnings;
    }

    private function timeValue(mixed $value): ?string
    {
        return $value ? substr((string) $value, 0, 5) : null;
    }
}

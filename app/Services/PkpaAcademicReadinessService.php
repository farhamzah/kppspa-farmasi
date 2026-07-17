<?php

namespace App\Services;

use App\Models\PkpaRotationAcademicReadinessReview;
use App\Models\PkpaRotationRun;
use App\Models\User;

class PkpaAcademicReadinessService
{
    public function __construct(
        private readonly PkpaAuditService $audit,
        private readonly PkpaAcademicNotificationService $notifications
    )
    {
    }

    public function review(PkpaRotationRun $run, ?User $actor, string $source = 'manual'): PkpaRotationAcademicReadinessReview
    {
        $run->loadMissing(['competencyRecords', 'specialTasks', 'rotationReport']);
        $issues = [];
        $operationalComplete = $run->status === 'operational_complete';
        if (! $operationalComplete) {
            $issues[] = 'Operasional rotasi belum selesai.';
        }

        $requiredCompetencies = $run->competencyRecords->where('is_required_snapshot', true);
        $verifiedCompetencies = $requiredCompetencies->where('status', 'verified');
        if ($verifiedCompetencies->count() < $requiredCompetencies->count()) {
            $issues[] = 'Kompetensi wajib belum seluruhnya verified.';
        }
        if ($run->competencyRecords->where('status', 'revision_requested')->count() > 0) {
            $issues[] = 'Ada kompetensi yang masih revision requested.';
        }

        $requiredTasks = $run->specialTasks->where('is_required_snapshot', true)->where('status', '!=', 'cancelled');
        $approvedTasks = $requiredTasks->where('status', 'approved');
        if ($approvedTasks->count() < $requiredTasks->count()) {
            $issues[] = 'Tugas khusus wajib belum seluruhnya approved.';
        }

        $reportStatus = $run->rotationReport?->status;
        if ($run->rotationReport && $reportStatus !== 'approved') {
            $issues[] = 'Laporan rotasi belum approved.';
        }
        if ($reportStatus === 'revision_requested') {
            $issues[] = 'Laporan rotasi masih membutuhkan revisi.';
        }

        $status = count($issues) === 0 ? 'ready_for_assessment' : ($operationalComplete ? 'needs_attention' : 'assessment_blocked');
        $review = PkpaRotationAcademicReadinessReview::create([
            'pkpa_rotation_run_id' => $run->id,
            'status' => $status,
            'required_competency_count' => $requiredCompetencies->count(),
            'verified_competency_count' => $verifiedCompetencies->count(),
            'required_task_count' => $requiredTasks->count(),
            'approved_task_count' => $approvedTasks->count(),
            'report_status' => $reportStatus,
            'operational_complete' => $operationalComplete,
            'blocking_issues' => $issues,
            'snapshot' => ['source' => $source],
            'reviewed_by_core_user_id' => $actor?->core_user_id,
            'reviewed_at' => now(),
        ]);
        $this->audit->record($actor, 'pkpa_academic_readiness_checked', $review, null, ['status' => $status]);
        $this->notifications->notifyRun($run, 'pkpa_academic_readiness_'.$status, $review, ['student', 'field_supervisor', 'internal_supervisor']);

        return $review;
    }
}

<?php

namespace App\Services;

use App\Models\PkpaRotationOperationRule;
use App\Models\PkpaRotationProgressSnapshot;
use App\Models\PkpaRotationRun;
use Carbon\CarbonPeriod;

class PkpaRotationProgressService
{
    public function snapshot(PkpaRotationRun $run, string $generatedBy = 'system'): PkpaRotationProgressSnapshot
    {
        $run->loadMissing('requirement');
        $rule = $this->ruleFor($run);
        $today = now()->toDateString();
        $totalDays = $this->businessDays($run->scheduled_start_date, $run->scheduled_end_date);
        $elapsedDays = $this->businessDays($run->scheduled_start_date, min($today, $run->scheduled_end_date->toDateString()));
        $attendanceSubmitted = $run->attendanceRecords()->whereIn('submission_status', ['submitted', 'approved', 'revision_requested', 'rejected'])->count();
        $attendanceApproved = $run->attendanceRecords()->where('submission_status', 'approved')->count();
        $attendanceProblem = $run->attendanceRecords()->whereIn('submission_status', ['revision_requested', 'rejected'])->count();
        $expectedLogbooks = $this->expectedLogbooks($rule, $totalDays);
        $logbookSubmitted = $run->logbookEntries()->whereIn('status', ['submitted', 'approved', 'revision_requested', 'reviewed_by_internal'])->count();
        $logbookApproved = $run->logbookEntries()->whereIn('status', ['approved', 'reviewed_by_internal'])->count();
        $logbookRevision = $run->logbookEntries()->where('status', 'revision_requested')->count();

        $issues = [];
        if ($rule?->attendance_required && $attendanceApproved < max($elapsedDays, (int) ($rule->minimum_approved_attendance_days ?? 0))) {
            $issues[] = 'Presensi tervalidasi belum memenuhi target berjalan.';
        }
        if ($rule?->logbook_required && $logbookApproved < max(min($expectedLogbooks, $elapsedDays), (int) ($rule->minimum_logbook_entries ?? 0))) {
            $issues[] = 'Logbook tervalidasi belum memenuhi target.';
        }
        if ($attendanceProblem > 0 || $logbookRevision > 0) {
            $issues[] = 'Ada item yang membutuhkan revisi atau ditolak.';
        }

        $expectedUnits = ($rule?->attendance_required ? $totalDays : 0) + ($rule?->logbook_required ? $expectedLogbooks : 0);
        $approvedUnits = ($rule?->attendance_required ? $attendanceApproved : 0) + ($rule?->logbook_required ? $logbookApproved : 0);
        $percentage = $expectedUnits === 0 ? 100.0 : min(100, round(($approvedUnits / $expectedUnits) * 100, 2));
        $status = $run->status === 'operational_complete' ? 'complete' : (count($issues) ? 'attention' : ($percentage > 0 ? 'on_track' : 'not_started'));

        $snapshot = PkpaRotationProgressSnapshot::where('pkpa_rotation_run_id', $run->id)->whereDate('snapshot_date', $today)->first()
            ?: new PkpaRotationProgressSnapshot(['pkpa_rotation_run_id' => $run->id, 'snapshot_date' => $today]);

        $snapshot->fill([
            'scheduled_days_elapsed' => $elapsedDays,
            'scheduled_days_total' => $totalDays,
            'attendance_expected_count' => $rule?->attendance_required ? $totalDays : 0,
            'attendance_submitted_count' => $attendanceSubmitted,
            'attendance_approved_count' => $attendanceApproved,
            'attendance_problem_count' => $attendanceProblem,
            'logbook_expected_count' => $expectedLogbooks,
            'logbook_submitted_count' => $logbookSubmitted,
            'logbook_approved_count' => $logbookApproved,
            'logbook_revision_count' => $logbookRevision,
            'progress_percentage' => $percentage,
            'progress_status' => $status,
            'blocking_issues' => $issues,
            'generated_by' => $generatedBy,
            'generated_at' => now(),
        ])->save();

        return $snapshot->refresh();
    }

    public function isReadyForOperationalCompletion(PkpaRotationRun $run): array
    {
        $snapshot = $this->snapshot($run, 'completion_check');
        $ready = $snapshot->attendance_problem_count === 0
            && $snapshot->logbook_revision_count === 0
            && $snapshot->progress_percentage >= 100;

        return [$ready, $snapshot->blocking_issues ?? []];
    }

    public function ruleFor(PkpaRotationRun $run): ?PkpaRotationOperationRule
    {
        $programDomainId = $run->requirement?->pkpa_program_domain_id;

        return $programDomainId
            ? PkpaRotationOperationRule::where('pkpa_program_domain_id', $programDomainId)->where('is_active', true)->first()
            : null;
    }

    private function expectedLogbooks(?PkpaRotationOperationRule $rule, int $businessDays): int
    {
        if (! $rule?->logbook_required) {
            return 0;
        }

        return match ($rule->logbook_frequency) {
            'weekly' => (int) ceil($businessDays / 5),
            'flexible' => (int) $rule->minimum_logbook_entries,
            default => $businessDays,
        };
    }

    private function businessDays($start, $end): int
    {
        if (! $start || ! $end || $end < $start) {
            return 0;
        }

        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (! $date->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }
}

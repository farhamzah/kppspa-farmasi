<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PkpaIntegrityAuditCommand extends Command
{
    protected $signature = 'pkpa:integrity-audit {--json : Output JSON}';

    protected $description = 'Dry-run data integrity audit for MY PSPA PKPA records.';

    public function handle(): int
    {
        $checks = [];

        $this->addCheck($checks, 'enrollment_without_requirement', 'High', $this->countEnrollmentWithoutRequirement());
        $this->addCheck($checks, 'duplicate_enrollment_requirement', 'High', $this->countDuplicateEnrollmentRequirement());
        $this->addCheck($checks, 'duplicate_active_group_member', 'Medium', $this->countDuplicateActiveGroupMember());
        $this->addCheck($checks, 'assignment_orphan_requirement', 'High', $this->countMissingForeign('pkpa_rotation_assignments', 'pkpa_enrollment_requirement_id', 'pkpa_enrollment_requirements'));
        $this->addCheck($checks, 'duplicate_assignment_per_plan_requirement', 'High', $this->countDuplicatePairs('pkpa_rotation_assignments', ['pkpa_placement_plan_id', 'pkpa_enrollment_requirement_id']));
        $this->addCheck($checks, 'multiple_current_placement_plan_per_program', 'High', $this->countDuplicateCurrent('pkpa_placement_plans', 'pkpa_program_id'));
        $this->addCheck($checks, 'publication_without_assignment', 'High', $this->countPublicationWithoutAssignment());
        $this->addCheck($checks, 'multiple_current_publication_per_program', 'High', $this->countDuplicateCurrent('pkpa_placement_publications', 'pkpa_program_id'));
        $this->addCheck($checks, 'runtime_without_publication', 'High', $this->countMissingForeign('pkpa_rotation_runs', 'current_placement_publication_id', 'pkpa_placement_publications'));
        $this->addCheck($checks, 'duplicate_runtime_per_requirement', 'High', $this->countDuplicateCurrent('pkpa_rotation_runs', 'pkpa_enrollment_requirement_id'));
        $this->addCheck($checks, 'duplicate_attendance_per_rotation_date', 'Medium', $this->countDuplicateTriples('pkpa_attendance_records', ['pkpa_rotation_run_id', 'attendance_date', 'active_key']));
        $this->addCheck($checks, 'logbook_orphan_rotation', 'High', $this->countMissingForeign('pkpa_logbook_entries', 'pkpa_rotation_run_id', 'pkpa_rotation_runs'));
        $this->addCheck($checks, 'missing_logbook_attachment_file', 'Medium', $this->countMissingAttachmentRows());
        $this->addCheck($checks, 'competency_orphan_rotation', 'High', $this->countMissingForeign('pkpa_rotation_competency_records', 'pkpa_rotation_run_id', 'pkpa_rotation_runs'));
        $this->addCheck($checks, 'task_without_template_snapshot', 'Medium', $this->countTaskWithoutTemplateSnapshot());
        $this->addCheck($checks, 'report_current_version_missing', 'High', $this->countReportCurrentVersionMissing());
        $this->addCheck($checks, 'assessment_without_scheme', 'High', $this->countMissingForeign('pkpa_rotation_assessments', 'source_assessment_scheme_id', 'pkpa_assessment_schemes'));
        $this->addCheck($checks, 'score_without_assessor_when_required', 'Medium', $this->countComponentScoreWithoutAssessor());
        $this->addCheck($checks, 'grade_without_assessment', 'High', $this->countMissingForeign('pkpa_rotation_grade_results', 'pkpa_rotation_assessment_id', 'pkpa_rotation_assessments'));
        $this->addCheck($checks, 'final_result_without_source', 'High', $this->countFinalResultWithoutSource());
        $this->addCheck($checks, 'passed_without_all_requirements_completed', 'High', $this->countPassedWithoutCompletedRequirements());
        $this->addCheck($checks, 'generated_document_without_version', 'High', $this->countGeneratedDocumentWithoutVersion());
        $this->addCheck($checks, 'duplicate_document_number', 'High', $this->countDuplicateNonNull('pkpa_generated_documents', 'document_number'));
        $this->addCheck($checks, 'duplicate_document_current_key', 'High', $this->countDuplicateNonNull('pkpa_document_templates', 'current_key'));
        $this->addCheck($checks, 'duplicate_notification_key', 'Medium', $this->countDuplicateNonNull('pkpa_notification_deliveries', 'notification_key'));

        $summary = [
            'mode' => 'dry-run',
            'auto_fix' => false,
            'checked_at' => now()->toIso8601String(),
            'checks_total' => count($checks),
            'issue_count' => array_sum(array_column($checks, 'count')),
            'critical_open' => $this->sumBySeverity($checks, 'Critical'),
            'high_open' => $this->sumBySeverity($checks, 'High'),
            'medium_open' => $this->sumBySeverity($checks, 'Medium'),
            'low_open' => $this->sumBySeverity($checks, 'Low'),
        ];

        $payload = [
            'status' => $summary['critical_open'] + $summary['high_open'] > 0 ? 'failed' : 'passed',
            'summary' => $summary,
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            $this->info('MY PSPA PKPA Integrity Audit - dry-run');
            $this->line('Status: '.$payload['status']);
            $this->line('Open issues: '.$summary['issue_count']);
            $this->line('Critical: '.$summary['critical_open'].' | High: '.$summary['high_open'].' | Medium: '.$summary['medium_open'].' | Low: '.$summary['low_open']);
        }

        return $payload['status'] === 'passed' ? self::SUCCESS : self::FAILURE;
    }

    private function addCheck(array &$checks, string $code, string $severity, int $count): void
    {
        $checks[] = [
            'code' => $code,
            'severity' => $severity,
            'count' => $count,
            'status' => $count === 0 ? 'passed' : 'failed',
        ];
    }

    private function sumBySeverity(array $checks, string $severity): int
    {
        return array_sum(array_map(
            fn (array $check): int => $check['severity'] === $severity ? $check['count'] : 0,
            $checks
        ));
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function countEnrollmentWithoutRequirement(): int
    {
        if (! $this->tableExists('pkpa_enrollments') || ! $this->tableExists('pkpa_enrollment_requirements')) {
            return 0;
        }

        return DB::table('pkpa_enrollments as e')
            ->leftJoin('pkpa_enrollment_requirements as r', 'r.pkpa_enrollment_id', '=', 'e.id')
            ->whereNull('r.id')
            ->count();
    }

    private function countDuplicateEnrollmentRequirement(): int
    {
        return $this->countDuplicatePairs('pkpa_enrollment_requirements', ['pkpa_enrollment_id', 'pkpa_program_domain_id']);
    }

    private function countDuplicateActiveGroupMember(): int
    {
        if (! $this->tableExists('pkpa_student_group_members')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table('pkpa_student_group_members')
                    ->select('pkpa_enrollment_id', DB::raw('count(*) as aggregate'))
                    ->where('status', 'active')
                    ->groupBy('pkpa_enrollment_id')
                    ->having('aggregate', '>', 1),
                'duplicates'
            )
            ->count();
    }

    private function countPublicationWithoutAssignment(): int
    {
        if (! $this->tableExists('pkpa_placement_publications') || ! $this->tableExists('pkpa_published_assignments')) {
            return 0;
        }

        return DB::table('pkpa_placement_publications as p')
            ->leftJoin('pkpa_published_assignments as a', 'a.pkpa_placement_publication_id', '=', 'p.id')
            ->whereNull('a.id')
            ->whereIn('p.status', ['published', 'current'])
            ->count();
    }

    private function countMissingAttachmentRows(): int
    {
        if (! $this->tableExists('pkpa_logbook_attachments')) {
            return 0;
        }

        return DB::table('pkpa_logbook_attachments')
            ->where(function ($query): void {
                $query->whereNull('disk')->orWhereNull('path')->orWhere('path', '');
            })
            ->where('status', 'active')
            ->count();
    }

    private function countTaskWithoutTemplateSnapshot(): int
    {
        if (! $this->tableExists('pkpa_rotation_special_tasks')) {
            return 0;
        }

        return DB::table('pkpa_rotation_special_tasks')
            ->where(function ($query): void {
                $query->whereNull('task_code_snapshot')->orWhereNull('task_title_snapshot');
            })
            ->count();
    }

    private function countReportCurrentVersionMissing(): int
    {
        if (! $this->tableExists('pkpa_rotation_reports') || ! $this->tableExists('pkpa_rotation_report_versions')) {
            return 0;
        }

        return DB::table('pkpa_rotation_reports as r')
            ->whereNotNull('r.current_version_id')
            ->leftJoin('pkpa_rotation_report_versions as v', 'v.id', '=', 'r.current_version_id')
            ->whereNull('v.id')
            ->count();
    }

    private function countComponentScoreWithoutAssessor(): int
    {
        if (! $this->tableExists('pkpa_rotation_component_scores')) {
            return 0;
        }

        return DB::table('pkpa_rotation_component_scores')
            ->whereNull('assessor_assignment_id')
            ->whereNotIn('status', ['not_started', 'calculated'])
            ->count();
    }

    private function countFinalResultWithoutSource(): int
    {
        if (! $this->tableExists('pkpa_final_grade_results')) {
            return 0;
        }

        return DB::table('pkpa_final_grade_results')
            ->where(function ($query): void {
                $query->whereNull('source_snapshot')->orWhereNull('calculation_snapshot');
            })
            ->count();
    }

    private function countPassedWithoutCompletedRequirements(): int
    {
        if (! $this->tableExists('pkpa_graduation_decisions') || ! $this->tableExists('pkpa_enrollment_requirements')) {
            return 0;
        }

        return DB::table('pkpa_graduation_decisions as d')
            ->where('d.decision', 'passed')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('pkpa_enrollment_requirements as r')
                    ->whereColumn('r.pkpa_enrollment_id', 'd.pkpa_enrollment_id')
                    ->where('r.status', '!=', 'completed');
            })
            ->count();
    }

    private function countGeneratedDocumentWithoutVersion(): int
    {
        if (! $this->tableExists('pkpa_generated_documents') || ! $this->tableExists('pkpa_generated_document_versions')) {
            return 0;
        }

        return DB::table('pkpa_generated_documents as d')
            ->leftJoin('pkpa_generated_document_versions as v', 'v.pkpa_generated_document_id', '=', 'd.id')
            ->whereIn('d.status', ['generated', 'approved', 'published'])
            ->whereNull('v.id')
            ->count();
    }

    private function countMissingForeign(string $table, string $column, string $foreignTable): int
    {
        if (! $this->tableExists($table) || ! $this->tableExists($foreignTable)) {
            return 0;
        }

        return DB::table($table.' as local')
            ->leftJoin($foreignTable.' as foreign', 'foreign.id', '=', 'local.'.$column)
            ->whereNotNull('local.'.$column)
            ->whereNull('foreign.id')
            ->count();
    }

    private function countDuplicatePairs(string $table, array $columns): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table($table)
                    ->select(array_merge($columns, [DB::raw('count(*) as aggregate')]))
                    ->groupBy($columns)
                    ->having('aggregate', '>', 1),
                'duplicates'
            )
            ->count();
    }

    private function countDuplicateTriples(string $table, array $columns): int
    {
        return $this->countDuplicatePairs($table, $columns);
    }

    private function countDuplicateCurrent(string $table, string $scopeColumn): int
    {
        if (! $this->tableExists($table) || ! Schema::hasColumn($table, 'current_key')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table($table)
                    ->select($scopeColumn, DB::raw('count(*) as aggregate'))
                    ->whereNotNull('current_key')
                    ->groupBy($scopeColumn)
                    ->having('aggregate', '>', 1),
                'duplicates'
            )
            ->count();
    }

    private function countDuplicateNonNull(string $table, string $column): int
    {
        if (! $this->tableExists($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                DB::table($table)
                    ->select($column, DB::raw('count(*) as aggregate'))
                    ->whereNotNull($column)
                    ->groupBy($column)
                    ->having('aggregate', '>', 1),
                'duplicates'
            )
            ->count();
    }
}

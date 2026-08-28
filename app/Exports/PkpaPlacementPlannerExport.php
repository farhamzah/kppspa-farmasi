<?php

namespace App\Exports;

use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementValidationIssue;
use App\Models\PkpaRotationAssignment;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class PkpaPlacementPlannerExport implements WithMultipleSheets
{
    public function __construct(private readonly PkpaPlacementPlan $plan)
    {
        $this->plan->loadMissing('program.domains.practiceDomain');
    }

    public function sheets(): array
    {
        return [
            new PkpaArraySheet('Matriks Penempatan', $this->matrixRows()),
            new PkpaArraySheet('Detail Penempatan', $this->detailRows()),
            new PkpaArraySheet('Ringkasan Kapasitas', $this->capacityRows()),
            new PkpaArraySheet('Ringkasan Pembimbing', $this->supervisorRows()),
            new PkpaArraySheet('Masalah Validasi', $this->issueRows()),
        ];
    }

    private function matrixRows(): array
    {
        $domains = $this->plan->program->domains()->with('practiceDomain')->orderBy('sort_order')->get();
        $rows = [['Rancangan Internal - Belum Dipublikasikan'], ['Program', $this->plan->program->name], ['Versi', $this->plan->version_number], []];
        $rows[] = array_merge(['NPM', 'Nama', 'Kelompok'], $domains->map(fn ($domain) => $domain->practiceDomain?->name)->all());
        $assignments = $this->plan->assignments()->with(['enrollment.activeGroupMembership.group', 'practiceDomain', 'selectedOption', 'practiceSite', 'supervisors'])->get()->groupBy('pkpa_enrollment_id');

        foreach ($this->plan->program->enrollments()->where('status', 'active')->with('activeGroupMembership.group')->orderBy('student_number')->get() as $enrollment) {
            $studentAssignments = $assignments->get($enrollment->id, collect());
            $row = [
                $enrollment->student_number,
                $enrollment->student_name_snapshot,
                $enrollment->activeGroupMembership?->group?->name,
            ];
            foreach ($domains as $domain) {
                $assignment = $studentAssignments->firstWhere('practice_domain_id', $domain->practice_domain_id);
                $row[] = $assignment ? $this->assignmentLabel($assignment) : 'Belum ditempatkan';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function detailRows(): array
    {
        $rows = [['Rancangan Internal - Belum Dipublikasikan'], []];
        $rows[] = ['Mahasiswa', 'NPM', 'Wahana', 'Option', 'Tempat', 'Tanggal', 'Durasi', 'PD', 'PL', 'Status', 'Masalah'];
        foreach ($this->plan->assignments()->with(['enrollment', 'practiceDomain', 'selectedOption', 'practiceSite', 'supervisors'])->get() as $assignment) {
            $rows[] = [
                $assignment->enrollment?->student_name_snapshot,
                $assignment->enrollment?->student_number,
                $assignment->practiceDomain?->name,
                $assignment->selectedOption?->name,
                $assignment->practiceSite?->name,
                optional($assignment->start_date)->format('d M Y').' - '.optional($assignment->end_date)->format('d M Y'),
                $assignment->calculated_effective_days.' hari efektif / '.($assignment->calculated_practice_hours ?? '-').' jam',
                $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->display_name,
                $assignment->supervisors->firstWhere('supervisor_type', 'field')?->display_name,
                $assignment->statusLabel(),
                PkpaPlacementValidationIssue::where('pkpa_rotation_assignment_id', $assignment->id)->where('is_resolved', false)->count(),
            ];
        }

        return $rows;
    }

    private function capacityRows(): array
    {
        $rows = [['Rancangan Internal - Belum Dipublikasikan'], []];
        $rows[] = ['Tempat', 'Availability', 'Kapasitas', 'Reserved', 'Terpakai', 'Sisa'];
        foreach ($this->plan->program->programSites()->with(['practiceSite', 'availabilityPeriods'])->get() as $site) {
            foreach ($site->availabilityPeriods as $period) {
                $used = $this->plan->assignments()->where('pkpa_site_availability_period_id', $period->id)->whereNotIn('status', ['cancelled', 'superseded'])->count();
                $usable = max(0, $period->maximum_students - $period->reserved_slots);
                $rows[] = [
                    $site->practiceSite?->name,
                    optional($period->start_date)->format('d M Y').' - '.optional($period->end_date)->format('d M Y'),
                    $period->maximum_students,
                    $period->reserved_slots,
                    $used,
                    max(0, $usable - $used),
                ];
            }
        }

        return $rows;
    }

    private function supervisorRows(): array
    {
        $rows = [['Rancangan Internal - Belum Dipublikasikan'], []];
        $rows[] = ['Pembimbing', 'Jenis', 'Wahana/Tempat', 'Beban', 'Batas', 'Status'];
        foreach ($this->plan->assignments()->with(['practiceDomain', 'practiceSite', 'supervisors'])->get()->flatMap->supervisors->groupBy('core_user_id') as $supervisors) {
            $first = $supervisors->first();
            $rows[] = [
                $first->display_name,
                $first->supervisor_type === 'internal' ? 'Pembimbing Dalam' : 'Preseptor',
                $first->supervisor_type === 'internal' ? $first->assignment?->practiceDomain?->name : $first->assignment?->practiceSite?->name,
                $supervisors->count(),
                '-',
                'Draft internal',
            ];
        }

        return $rows;
    }

    private function issueRows(): array
    {
        $rows = [['Rancangan Internal - Belum Dipublikasikan'], []];
        $rows[] = ['Mahasiswa', 'Wahana', 'Severity', 'Issue Code', 'Pesan', 'Saran'];
        foreach ($this->plan->validationRuns()->latest()->first()?->issues()->with('assignment.enrollment', 'assignment.practiceDomain')->get() ?? [] as $issue) {
            $rows[] = [
                $issue->assignment?->enrollment?->student_name_snapshot,
                $issue->assignment?->practiceDomain?->name,
                $issue->severity,
                $issue->issue_code,
                $issue->message,
                $issue->suggested_action,
            ];
        }

        return $rows;
    }

    private function assignmentLabel(PkpaRotationAssignment $assignment): string
    {
        return implode("\n", array_filter([
            $assignment->practiceSite?->name,
            optional($assignment->start_date)->format('d M Y').' - '.optional($assignment->end_date)->format('d M Y'),
            $assignment->supervisors->firstWhere('supervisor_type', 'internal')?->display_name ? 'PD: '.$assignment->supervisors->firstWhere('supervisor_type', 'internal')->display_name : null,
            $assignment->supervisors->firstWhere('supervisor_type', 'field')?->display_name ? 'PL: '.$assignment->supervisors->firstWhere('supervisor_type', 'field')->display_name : null,
            $assignment->statusLabel(),
        ]));
    }
}

class PkpaArraySheet implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(private readonly string $title, private readonly array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}

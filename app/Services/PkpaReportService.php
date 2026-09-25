<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PkpaReportService
{
    public function definitions(): array
    {
        return [
            'students' => [
                'title' => 'Daftar Mahasiswa PKPA',
                'description' => 'Mahasiswa yang terdaftar pada program/periode PKPA beserta status dan jumlah penempatannya.',
            ],
            'sites' => [
                'title' => 'Daftar Wahana dan Tempat PKPA',
                'description' => 'Tempat praktik yang digunakan pada jadwal resmi beserta periode dan jumlah mahasiswa.',
            ],
            'placements' => [
                'title' => 'Penempatan Mahasiswa dan Wahana',
                'description' => 'Daftar resmi mahasiswa, wahana, tempat, periode, pembimbing dalam, dan preseptor.',
            ],
            'supervisors' => [
                'title' => 'Pembimbing dan Mahasiswa Bimbingan',
                'description' => 'Rekap pembimbing dalam dan preseptor pada setiap penempatan mahasiswa.',
            ],
            'operations' => [
                'title' => 'Pelaksanaan, Presensi, dan Logbook',
                'description' => 'Ringkasan pelaksanaan PKPA, presensi, serta logbook per mahasiswa dan wahana.',
            ],
            'portfolios' => [
                'title' => 'Status Portofolio PKPA',
                'description' => 'Kemajuan portofolio setiap mahasiswa pada masing-masing wahana.',
            ],
            'assessments' => [
                'title' => 'Status Penilaian PKPA',
                'description' => 'Status pengisian, finalisasi, dan hasil penilaian setiap penempatan.',
            ],
        ];
    }

    public function summary(): array
    {
        return [
            'Program PKPA' => PkpaProgram::count(),
            'Mahasiswa aktif' => PkpaEnrollment::whereIn('status', ['active', 'on_hold'])->count(),
            'Penempatan resmi' => $this->assignmentQuery(request())->count(),
            'Tempat digunakan' => $this->assignmentQuery(request())->distinct()->count('practice_site_id'),
        ];
    }

    public function filterOptions(): array
    {
        return [
            'programs' => PkpaProgram::query()->orderByDesc('start_date')->get(),
            'domains' => PkpaPracticeDomain::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'sites' => PkpaPracticeSite::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function rows(string $type, Request $request): Collection
    {
        return match ($type) {
            'students' => $this->studentRows($request),
            'sites' => $this->siteRows($request),
            'placements' => $this->placementRows($request),
            'supervisors' => $this->supervisorRows($request),
            'operations' => $this->operationRows($request),
            'portfolios' => $this->portfolioRows($request),
            'assessments' => $this->assessmentRows($request),
            default => collect(),
        };
    }

    private function studentRows(Request $request): Collection
    {
        $assignmentEnrollmentIds = $this->assignmentQuery($request)->pluck('pkpa_enrollment_id');

        return PkpaEnrollment::query()
            ->with('program')
            ->when($request->filled('program'), fn (Builder $query) => $query->where('pkpa_program_id', $request->integer('program')))
            ->when($request->filled('domain') || $request->filled('site'), fn (Builder $query) => $query->whereIn('id', $assignmentEnrollmentIds))
            ->when($request->filled('q'), fn (Builder $query) => $query->search((string) $request->q))
            ->orderBy('student_name_snapshot')
            ->get()
            ->map(function (PkpaEnrollment $enrollment) use ($request) {
                $placements = $this->assignmentQuery($request)
                    ->where('pkpa_enrollment_id', $enrollment->id)
                    ->get();

                return [
                    'Program/Periode' => $enrollment->program?->name ?? '-',
                    'NIM' => $enrollment->student_number ?: '-',
                    'Nama Mahasiswa' => $enrollment->student_name_snapshot ?: '-',
                    'Email' => $enrollment->student_email_snapshot ?: '-',
                    'Status Peserta' => $enrollment->statusLabel(),
                    'Jumlah Penempatan' => $placements->count(),
                    'Wahana' => $placements->pluck('practice_domain_name_snapshot')->filter()->unique()->implode(', ') ?: '-',
                ];
            });
    }

    private function siteRows(Request $request): Collection
    {
        return $this->assignmentQuery($request)
            ->with('supervisors')
            ->orderBy('practice_domain_name_snapshot')
            ->orderBy('practice_site_name_snapshot')
            ->get()
            ->groupBy(fn (PkpaPublishedAssignment $assignment) => implode('|', [
                $assignment->pkpa_placement_publication_id,
                $assignment->practice_site_id,
                $assignment->start_date?->toDateString(),
                $assignment->end_date?->toDateString(),
            ]))
            ->map(function (Collection $assignments) {
                $first = $assignments->first();

                return [
                    'Program/Periode' => $first->publication?->program?->name ?? '-',
                    'Wahana' => $first->practice_domain_name_snapshot ?: '-',
                    'Tempat Praktik' => $first->practice_site_name_snapshot ?: '-',
                    'Alamat' => $first->practice_site_address_snapshot ?: '-',
                    'Tanggal Mulai' => $this->date($first->start_date),
                    'Tanggal Selesai' => $this->date($first->end_date),
                    'Jumlah Mahasiswa' => $assignments->count(),
                    'Preseptor' => $assignments->flatMap->supervisors
                        ->where('supervisor_type', 'field')->pluck('name_snapshot')->filter()->unique()->implode(', ') ?: 'Belum ditentukan',
                ];
            })->values();
    }

    private function placementRows(Request $request): Collection
    {
        return $this->assignmentQuery($request)
            ->with('supervisors')
            ->orderBy('practice_domain_name_snapshot')
            ->orderBy('practice_site_name_snapshot')
            ->orderBy('student_name_snapshot')
            ->get()
            ->map(fn (PkpaPublishedAssignment $assignment) => [
                'Program/Periode' => $assignment->publication?->program?->name ?? '-',
                'NIM' => $assignment->student_number_snapshot ?: '-',
                'Nama Mahasiswa' => $assignment->student_name_snapshot ?: '-',
                'Wahana' => $assignment->practice_domain_name_snapshot ?: '-',
                'Tempat Praktik' => $assignment->practice_site_name_snapshot ?: '-',
                'Mulai' => $this->date($assignment->start_date),
                'Selesai' => $this->date($assignment->end_date),
                'Pembimbing Dalam' => $this->supervisorName($assignment, 'internal'),
                'Preseptor' => $this->supervisorName($assignment, 'field'),
            ]);
    }

    private function supervisorRows(Request $request): Collection
    {
        return $this->assignmentQuery($request)
            ->with('supervisors')
            ->orderBy('practice_domain_name_snapshot')
            ->orderBy('student_name_snapshot')
            ->get()
            ->map(fn (PkpaPublishedAssignment $assignment) => [
                'Program/Periode' => $assignment->publication?->program?->name ?? '-',
                'Wahana' => $assignment->practice_domain_name_snapshot ?: '-',
                'Tempat Praktik' => $assignment->practice_site_name_snapshot ?: '-',
                'NIM' => $assignment->student_number_snapshot ?: '-',
                'Mahasiswa' => $assignment->student_name_snapshot ?: '-',
                'Pembimbing Dalam' => $this->supervisorName($assignment, 'internal'),
                'Preseptor' => $this->supervisorName($assignment, 'field'),
                'Periode Praktik' => $this->date($assignment->start_date).' - '.$this->date($assignment->end_date),
            ]);
    }

    private function operationRows(Request $request): Collection
    {
        return $this->runQuery($request)
            ->with(['program', 'enrollment', 'practiceDomain', 'practiceSite', 'attendanceRecords', 'logbookEntries'])
            ->orderBy('scheduled_start_date')
            ->get()
            ->map(function (PkpaRotationRun $run) {
                $attendance = $run->attendanceRecords;
                $logbooks = $run->logbookEntries;

                return [
                    'Program/Periode' => $run->program?->name ?? '-',
                    'NIM' => $run->enrollment?->student_number ?? '-',
                    'Mahasiswa' => $run->studentDisplayName(),
                    'Wahana' => $run->practiceDomain?->name ?? '-',
                    'Tempat Praktik' => $run->practiceSite?->name ?? '-',
                    'Status Pelaksanaan' => $this->label($run->operational_status ?: $run->status),
                    'Total Presensi' => $attendance->count(),
                    'Presensi Disetujui' => $attendance->where('status', 'approved')->count(),
                    'Total Logbook' => $logbooks->count(),
                    'Logbook Selesai' => $logbooks->whereIn('status', ['approved', 'internal_approved', 'locked'])->count(),
                ];
            });
    }

    private function portfolioRows(Request $request): Collection
    {
        return $this->runQuery($request)
            ->with(['program', 'enrollment', 'practiceDomain', 'practiceSite', 'currentPortfolio.sectionRecords'])
            ->orderBy('scheduled_start_date')
            ->get()
            ->map(function (PkpaRotationRun $run) {
                $portfolio = $run->currentPortfolio;

                return [
                    'Program/Periode' => $run->program?->name ?? '-',
                    'NIM' => $run->enrollment?->student_number ?? '-',
                    'Mahasiswa' => $run->studentDisplayName(),
                    'Wahana' => $run->practiceDomain?->name ?? '-',
                    'Tempat Praktik' => $run->practiceSite?->name ?? '-',
                    'Status Portofolio' => $portfolio?->statusLabel() ?? 'Belum dibuat',
                    'Bagian Terisi' => $portfolio?->sectionRecords->count() ?? 0,
                    'Dikirim' => $portfolio?->submitted_at?->format('d/m/Y H:i') ?? '-',
                    'Disetujui' => $portfolio?->internal_approved_at?->format('d/m/Y H:i') ?? '-',
                ];
            });
    }

    private function assessmentRows(Request $request): Collection
    {
        return $this->runQuery($request)
            ->with(['program', 'enrollment', 'practiceDomain', 'practiceSite', 'rotationAssessment.gradeResult'])
            ->orderBy('scheduled_start_date')
            ->get()
            ->map(function (PkpaRotationRun $run) {
                $assessment = $run->rotationAssessment;
                $grade = $assessment?->gradeResult;

                return [
                    'Program/Periode' => $run->program?->name ?? '-',
                    'NIM' => $run->enrollment?->student_number ?? '-',
                    'Mahasiswa' => $run->studentDisplayName(),
                    'Wahana' => $run->practiceDomain?->name ?? '-',
                    'Tempat Praktik' => $run->practiceSite?->name ?? '-',
                    'Status Penilaian' => $assessment ? $this->label($assessment->status) : 'Belum tersedia',
                    'Kelengkapan' => $assessment ? $this->label($assessment->completion_status) : '-',
                    'Nilai Akhir' => $grade?->final_score ?? '-',
                    'Hasil' => $grade ? $this->label($grade->result_status) : '-',
                    'Dirilis' => $grade?->released_at?->format('d/m/Y H:i') ?? '-',
                ];
            });
    }

    private function assignmentQuery(Request $request): Builder
    {
        return PkpaPublishedAssignment::query()
            ->with('publication.program')
            ->whereHas('publication', fn (Builder $query) => $query->current())
            ->when($request->filled('program'), fn (Builder $query) => $query->whereHas('publication', fn (Builder $publication) => $publication->where('pkpa_program_id', $request->integer('program'))))
            ->when($request->filled('domain'), fn (Builder $query) => $query->where('practice_domain_id', $request->integer('domain')))
            ->when($request->filled('site'), fn (Builder $query) => $query->where('practice_site_id', $request->integer('site')))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $search = '%'.trim((string) $request->q).'%';
                $query->where(fn (Builder $sub) => $sub
                    ->where('student_number_snapshot', 'like', $search)
                    ->orWhere('student_name_snapshot', 'like', $search)
                    ->orWhere('practice_site_name_snapshot', 'like', $search));
            });
    }

    private function runQuery(Request $request): Builder
    {
        return PkpaRotationRun::query()
            ->whereHas('publication', fn (Builder $query) => $query->current())
            ->when($request->filled('program'), fn (Builder $query) => $query->where('pkpa_program_id', $request->integer('program')))
            ->when($request->filled('domain'), fn (Builder $query) => $query->where('practice_domain_id', $request->integer('domain')))
            ->when($request->filled('site'), fn (Builder $query) => $query->where('practice_site_id', $request->integer('site')))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $search = '%'.trim((string) $request->q).'%';
                $query->where(fn (Builder $sub) => $sub
                    ->whereHas('enrollment', fn (Builder $enrollment) => $enrollment
                        ->where('student_number', 'like', $search)
                        ->orWhere('student_name_snapshot', 'like', $search))
                    ->orWhereHas('practiceSite', fn (Builder $site) => $site->where('name', 'like', $search)));
            });
    }

    private function supervisorName(PkpaPublishedAssignment $assignment, string $type): string
    {
        return $assignment->supervisors
            ->where('supervisor_type', $type)
            ->where('status', 'assigned')
            ->pluck('name_snapshot')
            ->filter()
            ->unique()
            ->implode(', ') ?: 'Belum ditentukan';
    }

    private function date(mixed $date): string
    {
        return $date?->format('d/m/Y') ?? '-';
    }

    private function label(?string $status): string
    {
        return filled($status) ? str($status)->replace('_', ' ')->headline()->toString() : '-';
    }
}

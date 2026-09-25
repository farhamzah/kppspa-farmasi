<?php

namespace App\Http\Controllers\Management;

use App\Exports\KpRecapExport;
use App\Http\Controllers\Controller;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\PkpaProgram;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Services\PkpaReportService;
use App\Support\SimplePdfReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecapController extends Controller
{
    public function index(PkpaReportService $service): View
    {
        $reports = $service->definitions();

        return view('management.recaps.index', [
            'summary' => $service->summary(),
            'reports' => $reports,
            'reportCounts' => collect($reports)->mapWithKeys(fn (array $report, string $type) => [
                $type => $service->rows($type, request())->count(),
            ]),
            ...$service->filterOptions(request()),
        ]);
    }

    public function students(Request $request, PkpaReportService $service): View
    {
        return $this->table('Daftar Mahasiswa PKPA', 'students', $request, $service);
    }

    public function placements(Request $request, PkpaReportService $service): View
    {
        return $this->table('Penempatan Mahasiswa dan Wahana', 'placements', $request, $service);
    }

    public function sites(Request $request, PkpaReportService $service): View
    {
        return $this->table('Daftar Wahana dan Tempat PKPA', 'sites', $request, $service);
    }

    public function supervisors(Request $request, PkpaReportService $service): View
    {
        return $this->table('Pembimbing dan Mahasiswa Bimbingan', 'supervisors', $request, $service);
    }

    public function operations(Request $request, PkpaReportService $service): View
    {
        return $this->table('Pelaksanaan, Presensi, dan Logbook', 'operations', $request, $service);
    }

    public function portfolios(Request $request, PkpaReportService $service): View
    {
        return $this->table('Status Portofolio PKPA', 'portfolios', $request, $service);
    }

    public function assessments(Request $request, PkpaReportService $service): View
    {
        return $this->table('Status Penilaian PKPA', 'assessments', $request, $service);
    }

    public function preview(string $type, Request $request, PkpaReportService $service): View
    {
        abort_unless(array_key_exists($type, $this->types()), 404);

        return view('management.recaps.report-preview', [
            'title' => $this->types()[$type],
            'type' => $type,
            'rows' => $service->rows($type, $request),
            'filters' => $this->filterSummary($request),
            'printMode' => $request->boolean('print'),
        ]);
    }

    public function download(string $type, string $format, Request $request, PkpaReportService $service): Response|BinaryFileResponse
    {
        abort_unless(array_key_exists($type, $this->types()), 404);
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);

        $rows = $service->rows($type, $request);
        $title = $this->types()[$type];
        $filename = str($title)->lower()->replace(' ', '-')->append('-'.now()->format('Ymd-His'))->toString();

        if ($format === 'excel') {
            return Excel::download(new KpRecapExport($rows), $filename.'.xlsx');
        }

        if ($format === 'pdf') {
            $headings = array_keys(['No' => ''] + ($rows->first() ?? ['Data' => '']));

            return response(SimplePdfReport::table(
                $title,
                $this->filterSummary($request),
                $headings,
                $rows->values()->map(fn ($row, $index) => array_values(['No' => $index + 1] + $row))->all()
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        abort(404);
    }

    private function table(string $title, string $type, Request $request, PkpaReportService $service): View
    {
        return view('management.recaps.table', [
            'title' => $title,
            'type' => $type,
            'rows' => $service->rows($type, $request),
            ...$service->filterOptions($request),
            'filters' => $request->only(['program', 'domain', 'site', 'internal_supervisor', 'field_supervisor', 'q']),
        ]);
    }

    private function types(): array
    {
        return [
            'students' => 'Daftar Mahasiswa PKPA',
            'sites' => 'Daftar Wahana dan Tempat PKPA',
            'placements' => 'Penempatan Mahasiswa dan Wahana',
            'supervisors' => 'Pembimbing dan Mahasiswa Bimbingan',
            'operations' => 'Pelaksanaan, Presensi, dan Logbook',
            'portfolios' => 'Status Portofolio PKPA',
            'assessments' => 'Status Penilaian PKPA',
        ];
    }

    private function filterSummary(Request $request): array
    {
        $program = $request->filled('program') ? PkpaProgram::find($request->program)?->name : null;
        $domain = $request->filled('domain') ? PkpaPracticeDomain::find($request->domain)?->name : null;
        $site = $request->filled('site') ? PkpaPracticeSite::find($request->site)?->name : null;
        $internalSupervisor = $this->supervisorFilterName($request, 'internal_supervisor', 'internal');
        $fieldSupervisor = $this->supervisorFilterName($request, 'field_supervisor', 'field');

        return [
            'Program/Periode' => $program ?: 'Semua program',
            'Wahana' => $domain ?: 'Semua wahana',
            'Tempat Praktik' => $site ?: 'Semua tempat',
            'Pembimbing Dalam' => $internalSupervisor ?: 'Semua pembimbing',
            'Preseptor' => $fieldSupervisor ?: 'Semua preseptor',
            'Pencarian' => $request->filled('q') ? (string) $request->q : '-',
            'Dicetak pada' => now()->format('d M Y H:i'),
        ];
    }

    private function supervisorFilterName(Request $request, string $parameter, string $type): ?string
    {
        if (! $request->filled($parameter)) {
            return null;
        }

        return PkpaPublishedAssignmentSupervisor::query()
            ->where('supervisor_type', $type)
            ->where('core_user_id', (string) $request->input($parameter))
            ->value('name_snapshot');
    }
}

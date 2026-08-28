<?php

namespace App\Http\Controllers\Management;

use App\Exports\KpRecapExport;
use App\Http\Controllers\Controller;
use App\Models\KpPeriod;
use App\Services\KpRecapService;
use App\Support\SimplePdfReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecapController extends Controller
{
    public function index(KpRecapService $service): View
    {
        return view('management.recaps.index', ['summary' => $service->summary()]);
    }

    public function students(Request $request, KpRecapService $service): View
    {
        return $this->table('Rekap Mahasiswa PKPA', 'students', $request, $service);
    }

    public function placements(Request $request, KpRecapService $service): View
    {
        return $this->table('Rekap Penempatan PKPA', 'placements', $request, $service);
    }

    public function logbooks(Request $request, KpRecapService $service): View
    {
        return $this->table('Rekap Logbook PKPA', 'logbooks', $request, $service);
    }

    public function exams(Request $request, KpRecapService $service): View
    {
        return $this->table('Rekap Ujian PKPA', 'exams', $request, $service);
    }

    public function scores(Request $request, KpRecapService $service): View
    {
        return $this->table('Rekap Nilai PKPA', 'scores', $request, $service);
    }

    public function preview(string $type, Request $request, KpRecapService $service): View
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

    public function download(string $type, string $format, Request $request, KpRecapService $service): Response|BinaryFileResponse
    {
        abort_unless(array_key_exists($type, $this->types()), 404);
        abort_unless(in_array($format, ['word', 'excel', 'pdf'], true), 404);

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

        return response()
            ->view('management.recaps.report-word', [
                'title' => $title,
                'rows' => $rows,
                'filters' => $this->filterSummary($request),
            ], 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.doc"',
            ]);
    }

    private function table(string $title, string $type, Request $request, KpRecapService $service): View
    {
        return view('management.recaps.table', [
            'title' => $title,
            'type' => $type,
            'rows' => $service->rows($type, $request),
            'periods' => KpPeriod::latest()->get(),
            'filters' => $request->only(['period', 'status', 'q']),
        ]);
    }

    private function types(): array
    {
        return [
            'students' => 'Rekap Mahasiswa PKPA',
            'placements' => 'Rekap Penempatan PKPA',
            'logbooks' => 'Rekap Logbook PKPA',
            'exams' => 'Rekap Ujian PKPA',
            'scores' => 'Rekap Nilai PKPA',
        ];
    }

    private function filterSummary(Request $request): array
    {
        $period = $request->filled('period') ? KpPeriod::find($request->period)?->name : null;

        return [
            'Periode' => $period ?: 'Semua periode',
            'Status' => $request->filled('status') ? ucfirst(str_replace('_', ' ', (string) $request->status)) : 'Semua status',
            'Pencarian' => $request->filled('q') ? (string) $request->q : '-',
            'Dicetak pada' => now()->format('d M Y H:i'),
        ];
    }
}

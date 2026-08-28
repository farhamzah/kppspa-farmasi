<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Exports\KpRecapExport;
use App\Http\Requests\Management\CancelSelectionRequest;
use App\Http\Requests\Management\MoveSelectionRequest;
use App\Http\Requests\Management\StoreManualPlaceSelectionRequest;
use App\Models\KpPlaceQuota;
use App\Models\KpPlaceSelection;
use App\Models\KpRegistration;
use App\Services\KpPlaceSelectionService;
use App\Services\PlaceSelectionMonitoringReportService;
use App\Support\SimplePdfReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlaceSelectionMonitoringController extends Controller
{
    public function index(Request $request, PlaceSelectionMonitoringReportService $report): View
    {
        $selections = $report->query($request)
            ->paginate(10)
            ->withQueryString();

        return view('management.place-selections.index', [
            'selections' => $selections,
            'periods' => $report->periods(),
            'filters' => $request->only(['period', 'status', 'q']),
            'stats' => $report->stats(),
        ]);
    }

    public function reportPreview(Request $request, PlaceSelectionMonitoringReportService $report): View
    {
        return view('management.place-selections.report-preview', [
            'rows' => $report->rows($request),
            'stats' => $report->stats(),
            'filters' => $report->filterSummary($request),
            'printMode' => $request->boolean('print'),
        ]);
    }

    public function reportDownload(string $format, Request $request, PlaceSelectionMonitoringReportService $report): Response|BinaryFileResponse
    {
        abort_unless(in_array($format, ['word', 'excel', 'pdf'], true), 404);

        $rows = $report->rows($request);
        $filename = 'monitoring-pemilihan-tempat-'.now()->format('Ymd-His');

        if ($format === 'excel') {
            return Excel::download(new KpRecapExport($rows), $filename.'.xlsx');
        }

        if ($format === 'pdf') {
            $headings = array_keys($rows->first() ?? [
                'No' => '',
                'Mahasiswa' => '',
                'NIM' => '',
                'Periode' => '',
                'Tempat PKPA' => '',
                'Waktu Pilih' => '',
                'Status' => '',
            ]);

            return response(SimplePdfReport::table(
                'Monitoring Penetapan Tempat PKPA',
                $report->filterSummary($request),
                $headings,
                $rows->map(fn ($row) => array_values($row))->all()
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return response()
            ->view('management.place-selections.report-word', [
                'rows' => $rows,
                'stats' => $report->stats(),
                'filters' => $report->filterSummary($request),
            ], 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.doc"',
            ]);
    }

    public function show(KpPlaceSelection $selection): View
    {
        return view('management.place-selections.show', [
            'selection' => $selection->load(['period', 'registration', 'student.user', 'place', 'quota', 'selectedBy', 'cancelledBy', 'assignment']),
        ]);
    }

    public function manual(): View
    {
        return view('management.place-selections.manual', [
            'registrations' => $this->eligibleManualRegistrations(),
            'quotas' => $this->availableManualQuotas(),
        ]);
    }

    public function storeManual(StoreManualPlaceSelectionRequest $request, KpPlaceSelectionService $service): RedirectResponse
    {
        $selection = $service->selectPlaceManually(
            $request->user(),
            KpRegistration::findOrFail($request->integer('kp_registration_id')),
            KpPlaceQuota::findOrFail($request->integer('kp_place_quota_id')),
            $request->reason
        );

        return redirect()->route('management.place-selections.show', $selection)->with('status', 'Tempat PKPA mahasiswa berhasil ditetapkan secara manual.');
    }

    public function cancel(CancelSelectionRequest $request, KpPlaceSelection $selection, KpPlaceSelectionService $service): RedirectResponse
    {
        $service->cancelSelection($request->user(), $selection, $request->reason);

        return back()->with('status', 'Penetapan tempat PKPA berhasil dibatalkan.');
    }

    public function move(KpPlaceSelection $selection): View
    {
        return view('management.place-selections.move', [
            'selection' => $selection->load(['period', 'student.user', 'place']),
            'quotas' => KpPlaceQuota::with('place')
                ->where('kp_period_id', $selection->kp_period_id)
                ->where('is_open', true)
                ->get(),
        ]);
    }

    public function moveStore(MoveSelectionRequest $request, KpPlaceSelection $selection, KpPlaceSelectionService $service): RedirectResponse
    {
        $newSelection = $service->moveSelection($request->user(), $selection, KpPlaceQuota::findOrFail($request->kp_place_quota_id), $request->reason);

        return redirect()->route('management.place-selections.show', $newSelection)->with('status', 'Penetapan tempat PKPA berhasil dipindahkan.');
    }

    private function eligibleManualRegistrations()
    {
        return KpRegistration::query()
            ->with(['period.documentRequirements', 'documents', 'student.user'])
            ->where('status', 'terverifikasi')
            ->whereDoesntHave('activePlaceSelection')
            ->whereDoesntHave('assignment', fn ($query) => $query->where('status', '!=', 'dibatalkan'))
            ->latest()
            ->get()
            ->filter(fn (KpRegistration $registration): bool => $registration->isEligibleForPlaceSelection())
            ->values();
    }

    private function availableManualQuotas()
    {
        return KpPlaceQuota::query()
            ->with(['period', 'place'])
            ->where('is_open', true)
            ->latest()
            ->get()
            ->filter(fn (KpPlaceQuota $quota): bool => $quota->remainingQuota() > 0)
            ->values();
    }
}

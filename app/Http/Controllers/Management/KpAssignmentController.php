<?php

namespace App\Http\Controllers\Management;

use App\Exports\KpTableReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\AssignFieldSupervisorRequest;
use App\Http\Requests\Management\AssignInternalSupervisorRequest;
use App\Http\Requests\Management\CancelKpAssignmentRequest;
use App\Http\Requests\Management\StoreKpAssignmentRequest;
use App\Http\Requests\Management\UpdateKpAssignmentRequest;
use App\Models\FieldSupervisor;
use App\Models\KpAssignment;
use App\Models\KpPlaceSelection;
use App\Models\Lecturer;
use App\Services\KpAssignmentReportService;
use App\Services\KpAssignmentService;
use App\Services\KpCoreBridgeProvisioningService;
use App\Support\SimplePdfReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class KpAssignmentController extends Controller
{
    public function index(Request $request, KpAssignmentReportService $report): View
    {
        $assignments = $report->query($request)
            ->paginate(10)
            ->withQueryString();

        return view('management.assignments.index', [
            'assignments' => $assignments,
            'periods' => $report->periods(),
            'filters' => $request->only(['period', 'status', 'q', 'place', 'internal_supervisor', 'field_supervisor', 'sort']),
            'statusOptions' => $report->statusOptions(),
            'sortOptions' => $report->sortOptions(),
        ]);
    }

    public function reportPreview(Request $request, KpAssignmentReportService $report): View
    {
        return view('management.assignments.report-preview', [
            'rows' => $report->rows($request),
            'filters' => $report->filterSummary($request),
            'printMode' => $request->boolean('print'),
        ]);
    }

    public function reportDownload(string $format, Request $request, KpAssignmentReportService $report): Response|BinaryFileResponse
    {
        abort_unless(in_array($format, ['word', 'excel', 'pdf'], true), 404);

        $rows = $report->rows($request);
        $filename = 'penempatan-pkpa-'.now()->format('Ymd-His');
        $filters = $report->filterSummary($request);

        if ($format === 'excel') {
            return Excel::download(new KpTableReportExport('Penempatan PKPA', $filters, $rows), $filename.'.xlsx');
        }

        if ($format === 'pdf') {
            $headings = array_keys($rows->first() ?? [
                'No' => '',
                'Mahasiswa' => '',
                'NIM' => '',
                'Periode' => '',
                'Tempat PKPA' => '',
                'Pembimbing Dalam' => '',
                'Pembimbing Lapangan' => '',
                'Status' => '',
            ]);

            return response(SimplePdfReport::table(
                'Penempatan PKPA',
                $filters + ['Total data' => $rows->count()],
                $headings,
                $rows->map(fn ($row) => array_values($row))->all()
            ), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return response()
            ->view('management.assignments.report-word', [
                'rows' => $rows,
                'filters' => $filters,
            ], 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.doc"',
            ]);
    }

    public function create(): View
    {
        return view('management.assignments.create', $this->formData());
    }

    public function store(StoreKpAssignmentRequest $request, KpAssignmentService $service): RedirectResponse
    {
        $assignment = $service->createFromSelection(
            $request->user(),
            KpPlaceSelection::findOrFail($request->kp_place_selection_id),
            $request->integer('internal_supervisor_id') ?: null,
            $request->integer('field_supervisor_id') ?: null,
            $request->note
        );

        return redirect()->route('management.pkpa-assignments.index')->with('status', 'Penempatan PKPA berhasil dibuat.');
    }

    public function createFromSelection(Request $request, KpPlaceSelection $selection, KpAssignmentService $service): RedirectResponse
    {
        $assignment = $service->createFromSelection($request->user(), $selection);

        return redirect()->route('management.kp-assignments.edit', $assignment)->with('status', 'Penempatan dibuat. Silakan lengkapi pembimbing.');
    }

    public function show(KpAssignment $kpAssignment): View
    {
        return view('management.assignments.show', [
            'assignment' => $kpAssignment->load(['period', 'registration', 'selection', 'student.user', 'place', 'internalSupervisor.user', 'fieldSupervisor.user', 'logs.user']),
            'backUrl' => $this->safeAssignmentBackUrl(request('return_url')),
        ]);
    }

    public function edit(KpAssignment $kpAssignment): View
    {
        return view('management.assignments.edit', $this->formData() + [
            'assignment' => $kpAssignment,
            'backUrl' => $this->safeAssignmentBackUrl(request('return_url')),
        ]);
    }

    public function update(UpdateKpAssignmentRequest $request, KpAssignment $kpAssignment, KpAssignmentService $service): RedirectResponse
    {
        $service->updateSupervisors(
            $request->user(),
            $kpAssignment,
            $request->filled('internal_supervisor_id') ? Lecturer::findOrFail($request->internal_supervisor_id) : null,
            $request->filled('field_supervisor_id') ? FieldSupervisor::findOrFail($request->field_supervisor_id) : null,
            $request->note
        );

        return redirect()
            ->route('management.kp-assignments.show', array_filter([
                'kp_assignment' => $kpAssignment,
                'return_url' => $this->safeAssignmentBackUrl($request->input('return_url')),
            ]))
            ->with('status', 'Pembimbing penempatan berhasil diperbarui.');
    }

    public function assignInternalSupervisor(AssignInternalSupervisorRequest $request, KpAssignment $assignment, KpAssignmentService $service): RedirectResponse
    {
        $service->assignInternalSupervisor($request->user(), $assignment, Lecturer::findOrFail($request->internal_supervisor_id), $request->note);

        return back()->with('status', 'Pembimbing dalam berhasil ditetapkan.');
    }

    public function assignFieldSupervisor(AssignFieldSupervisorRequest $request, KpAssignment $assignment, KpAssignmentService $service): RedirectResponse
    {
        $service->assignFieldSupervisor($request->user(), $assignment, FieldSupervisor::findOrFail($request->field_supervisor_id), $request->note);

        return back()->with('status', 'Pembimbing lapangan berhasil ditetapkan.');
    }

    public function cancel(CancelKpAssignmentRequest $request, KpAssignment $assignment, KpAssignmentService $service): RedirectResponse
    {
        $service->cancelAssignment($request->user(), $assignment, $request->reason);

        return back()->with('status', 'Penempatan PKPA berhasil dibatalkan.');
    }

    private function formData(): array
    {
        $this->syncCoreFieldSupervisorsForDropdown();

        return [
            'selections' => KpPlaceSelection::query()
                ->with(['period', 'student.user', 'place'])
                ->where('status', 'aktif')
                ->whereDoesntHave('assignment')
                ->latest('selected_at')
                ->get(),
            'lecturers' => Lecturer::with('user')->whereHas('user.roles', fn ($q) => $q->where('name', 'pembimbing_dalam'))->orderBy('nidn_nip')->get(),
            'fieldSupervisors' => FieldSupervisor::with('user')
                ->where('status', 'active')
                ->whereHas('user.roles', fn ($q) => $q->where('name', 'pembimbing_lapangan'))
                ->orderBy('institution_name')
                ->get(),
        ];
    }

    private function syncCoreFieldSupervisorsForDropdown(): void
    {
        try {
            app(KpCoreBridgeProvisioningService::class)->syncFieldSupervisors(execute: true);
        } catch (Throwable $exception) {
            Log::warning('Failed to sync Core field supervisors for KP assignment dropdown.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function safeAssignmentBackUrl(?string $returnUrl): string
    {
        $fallback = route('management.pkpa-assignments.index');

        if (! $returnUrl) {
            return $fallback;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $returnHost = parse_url($returnUrl, PHP_URL_HOST);
        $returnPath = parse_url($returnUrl, PHP_URL_PATH);

        if ($returnHost && $appHost && $returnHost !== $appHost) {
            return $fallback;
        }

        if (! in_array($returnPath, ['/management/kp-assignments', '/management/pkpa-assignments'], true)) {
            return $fallback;
        }

        return $returnUrl;
    }
}

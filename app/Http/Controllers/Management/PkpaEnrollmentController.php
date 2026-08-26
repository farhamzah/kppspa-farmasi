<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\Pkpa\CancelPkpaEnrollmentRequest;
use App\Http\Requests\Management\Pkpa\PreviewPkpaEnrollmentImportRequest;
use App\Http\Requests\Management\Pkpa\StorePkpaEnrollmentRequest;
use App\Http\Requests\Management\Pkpa\UpdatePkpaEnrollmentStatusRequest;
use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentImportBatch;
use App\Models\PkpaProgram;
use App\Models\PkpaStudentGroup;
use App\Services\PkpaEnrollmentCoreSyncService;
use App\Services\PkpaEnrollmentImportService;
use App\Services\PkpaEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PkpaEnrollmentController extends Controller
{
    public function __construct(
        private readonly PkpaEnrollmentService $enrollmentService,
        private readonly PkpaEnrollmentCoreSyncService $syncService,
        private readonly PkpaEnrollmentImportService $importService,
    ) {
    }

    public function index(Request $request): View
    {
        $enrollments = PkpaEnrollment::query()
            ->with(['program', 'requirements', 'activeGroupMembership.group'])
            ->search($request->input('q'))
            ->when($request->filled('program_id'), fn ($query) => $query->where('pkpa_program_id', $request->program_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('core_account_status'), fn ($query) => $query->where('core_account_status_snapshot', $request->core_account_status))
            ->when($request->input('grouped') === 'yes', fn ($query) => $query->whereHas('activeGroupMembership'))
            ->when($request->input('grouped') === 'no', fn ($query) => $query->whereDoesntHave('activeGroupMembership'))
            ->when($request->input('sync') === 'problem', fn ($query) => $query->whereIn('last_core_sync_status', ['failed', 'warning']))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('management.pkpa-enrollments.index', [
            'enrollments' => $enrollments,
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'filters' => $request->only(['q', 'program_id', 'status', 'core_account_status', 'grouped', 'sync']),
        ]);
    }

    public function create(): View
    {
        return view('management.pkpa-enrollments.create', [
            'programs' => PkpaProgram::whereNotIn('status', ['completed', 'archived'])->orderByDesc('id')->get(),
            'groups' => PkpaStudentGroup::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StorePkpaEnrollmentRequest $request): RedirectResponse
    {
        $program = PkpaProgram::findOrFail($request->validated('pkpa_program_id'));
        $group = $request->filled('pkpa_student_group_id') ? PkpaStudentGroup::findOrFail($request->validated('pkpa_student_group_id')) : null;
        $selectedStudents = collect($request->validated('selected_students', []))
            ->pluck('core_user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if (count($selectedStudents) > 0) {
            $result = $this->enrollmentService->createMany($program, $selectedStudents, $group, $request->user(), $request->validated());
            $createdCount = $result['created']->count();

            if ($createdCount === 0) {
                return back()->withInput()->withErrors([
                    'selected_students' => collect($result['errors'])->values()->first() ?? 'Belum ada peserta yang berhasil ditambahkan.',
                ]);
            }

            $status = "{$createdCount} peserta berhasil ditambahkan.";
            if ($result['created']->first()?->requirements) {
                $status .= ' Kewajiban wahana otomatis sudah dibuat.';
            }

            $redirect = redirect()->route('management.pkpa-enrollments.index')->with('status', $status);

            if (count($result['errors']) > 0) {
                $redirect->with('warning', count($result['errors']).' data dilewati karena sudah terdaftar atau tidak valid.');
            }

            return $redirect;
        }

        $enrollment = $this->enrollmentService->create($program, $request->validated(), $group, $request->user());

        return redirect()
            ->route('management.pkpa-enrollments.show', $enrollment)
            ->with('status', 'Peserta berhasil ditambahkan. '.$enrollment->requirements->count().' kewajiban wahana berhasil dibuat.');
    }

    public function show(PkpaEnrollment $pkpaEnrollment): View
    {
        $pkpaEnrollment->load([
            'program',
            'requirements.practiceDomain',
            'requirements.selectedOption',
            'activeGroupMembership.group',
            'groupMemberships.group',
        ]);

        return view('management.pkpa-enrollments.show', ['enrollment' => $pkpaEnrollment]);
    }

    public function sync(PkpaEnrollment $pkpaEnrollment, Request $request): RedirectResponse
    {
        $synced = $this->syncService->syncOne($pkpaEnrollment, $request->user());

        return back()->with('status', $synced->last_core_sync_status === 'ok' ? 'Snapshot Core berhasil disinkronkan.' : 'Sinkronisasi selesai dengan peringatan.');
    }

    public function syncProgram(PkpaProgram $pkpaProgram, Request $request): RedirectResponse
    {
        $count = $this->syncService->syncProgram($pkpaProgram, $request->user());

        return back()->with('status', "{$count} peserta program disinkronkan dari Core.");
    }

    public function status(UpdatePkpaEnrollmentStatusRequest $request, PkpaEnrollment $pkpaEnrollment): RedirectResponse
    {
        $this->enrollmentService->changeStatus($pkpaEnrollment, $request->validated('status'), $request->user());

        return back()->with('status', 'Status kepesertaan berhasil diperbarui.');
    }

    public function cancel(CancelPkpaEnrollmentRequest $request, PkpaEnrollment $pkpaEnrollment): RedirectResponse
    {
        $this->enrollmentService->cancel($pkpaEnrollment, $request->validated('cancellation_reason'), $request->user());

        return back()->with('status', 'Kepesertaan berhasil dibatalkan dengan audit alasan.');
    }

    public function importForm(): View
    {
        return view('management.pkpa-enrollments.import', [
            'programs' => PkpaProgram::whereNotIn('status', ['completed', 'archived'])->orderByDesc('id')->get(),
            'batches' => PkpaEnrollmentImportBatch::with('program')->latest()->limit(10)->get(),
        ]);
    }

    public function importPreview(PreviewPkpaEnrollmentImportRequest $request): RedirectResponse
    {
        $batch = $this->importService->preview(
            PkpaProgram::findOrFail($request->validated('pkpa_program_id')),
            $request->file('file'),
            $request->user(),
        );

        return redirect()->route('management.pkpa-enrollment-imports.show', $batch)->with('status', 'Preview import selesai. Periksa row valid sebelum import final.');
    }

    public function importShow(PkpaEnrollmentImportBatch $batch): View
    {
        $batch->load(['program', 'rows']);

        return view('management.pkpa-enrollments.import-show', ['batch' => $batch]);
    }

    public function importRun(PkpaEnrollmentImportBatch $batch, Request $request): RedirectResponse
    {
        $this->importService->importValidRows($batch, $request->user());

        return back()->with('status', 'Row valid berhasil diproses. Row invalid tetap tidak dibuat sebagai peserta.');
    }

    public function template(): Response
    {
        return response($this->importService->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_peserta_pkpa.csv"',
        ]);
    }
}

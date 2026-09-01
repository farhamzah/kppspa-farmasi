<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreKpLogbookCommentRequest;
use App\Models\KpAssignment;
use App\Models\KpLogbook;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaPublishedAssignment;
use App\Services\KpLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogbookMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $coreUserId = $request->user()->core_user_id;
        $pkpaAssignments = PkpaPublishedAssignment::query()
            ->with([
                'rotationRuns' => fn ($query) => $query
                    ->with(['logbookEntries'])
                    ->whereNull('cancelled_at')
                    ->latest(),
            ])
            ->forSupervisor('internal', $coreUserId)
            ->whereHas('publication', fn ($query) => $query
                ->where('status', 'published')
                ->where('is_current', true))
            ->orderBy('start_date')
            ->get();

        if ($pkpaAssignments->isNotEmpty()) {
            $readyLogbooks = PkpaLogbookEntry::query()
                ->whereIn('status', ['field_approved', 'approved'])
                ->whereHas('rotationRun', fn ($query) => $query
                    ->forSupervisor('internal', $coreUserId)
                    ->whereNull('cancelled_at'))
                ->with(['rotationRun.practiceDomain', 'rotationRun.practiceSite', 'rotationRun.enrollment'])
                ->latest('entry_date')
                ->paginate(15)
                ->withQueryString();

            return view('internal-supervisor.pkpa-logbook-validation.index', [
                'assignments' => $pkpaAssignments,
                'readyLogbooks' => $readyLogbooks,
            ]);
        }

        /* Legacy KP monitoring retained below for legacy route actions. */
        $lecturerId = $request->user()->lecturer?->id ?: 0;
        $assignmentsQuery = KpAssignment::query()
            ->with(['student.user', 'period', 'place', 'fieldSupervisor.user'])
            ->withCount([
                'logbooks',
                'logbooks as pending_logbooks_count' => fn ($q) => $q->where('status', 'menunggu_validasi'),
                'logbooks as approved_logbooks_count' => fn ($q) => $q->where('status', 'disetujui'),
                'logbooks as revision_logbooks_count' => fn ($q) => $q->whereIn('status', ['revisi', 'ditolak']),
            ])
            ->withMax('logbooks', 'activity_date')
            ->withMax('logbooks', 'submitted_at')
            ->where('internal_supervisor_id', $lecturerId)
            ->when($request->filled('status'), fn ($q) => $q->whereHas('logbooks', fn ($logbook) => $logbook->where('status', $request->status)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->whereHas('student', fn ($student) => $student->where('nim', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")));
            })
            ->orderByDesc('pending_logbooks_count')
            ->orderByDesc('logbooks_max_activity_date');

        $assignments = $assignmentsQuery
            ->paginate(10)
            ->withQueryString();

        $selectedAssignment = null;
        $selectedLogbooks = collect();

        if ($request->filled('assignment')) {
            $selectedAssignment = KpAssignment::query()
                ->with(['student.user', 'period', 'place', 'fieldSupervisor.user'])
                ->where('internal_supervisor_id', $lecturerId)
                ->whereKey($request->integer('assignment'))
                ->first();

            $selectedLogbooks = $selectedAssignment
                ? $selectedAssignment->logbooks()
                    ->with(['comments.user', 'validatedBy'])
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                    ->latest('activity_date')
                    ->get()
                : collect();
        }

        return view('internal-supervisor.logbooks.index', [
            'assignments' => $assignments,
            'selectedAssignment' => $selectedAssignment,
            'selectedLogbooks' => $selectedLogbooks,
            'filters' => $request->only(['status', 'q', 'assignment']),
        ]);
    }

    public function show(KpLogbook $logbook, KpLogbookService $service): View
    {
        $service->ensureInternalSupervisorCanView(request()->user(), $logbook);

        return view('internal-supervisor.logbooks.show', [
            'logbook' => $logbook->load(['assignment.student.user', 'assignment.period', 'assignment.place', 'assignment.fieldSupervisor.user', 'comments.user', 'logs.user']),
        ]);
    }

    public function comments(StoreKpLogbookCommentRequest $request, KpLogbook $logbook, KpLogbookService $service): RedirectResponse
    {
        $service->ensureInternalSupervisorCanView($request->user(), $logbook);
        $service->addComment($request->user(), $logbook, $request->comment, $request->visibility);

        return back()->with('status', 'Komentar pemantauan berhasil ditambahkan.');
    }

    public function download(KpLogbook $logbook, KpLogbookService $service): StreamedResponse
    {
        $service->ensureInternalSupervisorCanView(request()->user(), $logbook);
        abort_unless($logbook->hasEvidenceFile(), 404);

        return Storage::disk($logbook->evidence_disk ?: 'local')->download($logbook->evidence_path, $logbook->evidence_original_filename);
    }
}

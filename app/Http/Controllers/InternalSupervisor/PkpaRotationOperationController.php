<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaLogbookAttachment;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationRun;
use App\Services\PkpaLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PkpaRotationOperationController extends Controller
{
    public function __construct(private readonly PkpaLogbookService $logbooks) {}

    public function index(Request $request): View
    {
        $coreUserId = $request->user()->core_user_id;
        $tab = in_array($request->query('tab'), ['overview', 'validation', 'history'], true)
            ? $request->query('tab')
            : (config('my_pkpa.preceptor_document_validation_enabled') ? 'overview' : 'validation');
        $runs = PkpaRotationRun::forSupervisor('internal', $coreUserId)
            ->whereNull('cancelled_at')
            ->with(['practiceDomain', 'practiceSite', 'enrollment', 'logbookEntries'])
            ->latest()
            ->get();
        $logbookQuery = PkpaLogbookEntry::query()
            ->whereHas('rotationRun', fn ($query) => $query
                ->forSupervisor('internal', $coreUserId)
                ->whereNull('cancelled_at'));
        $filteredRuns = $runs;
        if ($request->filled('q')) {
            $search = mb_strtolower(trim($request->string('q')->toString()));
            $filteredRuns = $filteredRuns->filter(fn ($run) => str_contains(mb_strtolower($run->studentDisplayName().' '.$run->studentDisplaySecondary().' '.$run->practiceSite?->name), $search));
        }
        if ($request->filled('domain')) {
            $filteredRuns = $filteredRuns->where('practice_domain_id', $request->integer('domain'));
        }
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => array_merge(['nullable', 'date'], $request->filled('date_from') ? ['after_or_equal:date_from'] : [])]);
        $filteredQuery = (clone $logbookQuery)->whereIn('pkpa_rotation_run_id', $filteredRuns->pluck('id'))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->input('date_to')));

        return view('internal-supervisor.pkpa-operations.index', [
            'runs' => $runs,
            'tab' => $tab,
            'readyLogbookCount' => (clone $logbookQuery)->whereIn('status', PkpaLogbookEntry::internalReviewStatuses())->count(),
            'completedLogbookCount' => (clone $logbookQuery)->where('status', 'internal_approved')->count(),
            'historyLogbookCount' => (clone $logbookQuery)->where('status', '!=', 'draft')->count(),
            'logbookEntries' => $tab === 'overview'
                ? null
                : (clone $filteredQuery)
                    ->when(
                        $tab === 'validation',
                        fn ($query) => $query->whereIn('status', PkpaLogbookEntry::internalReviewStatuses()),
                        fn ($query) => $query->where('status', '!=', 'draft')
                    )
                    ->with(['rotationRun.practiceDomain', 'rotationRun.practiceSite', 'rotationRun.enrollment'])
                    ->latest('entry_date')
                    ->paginate(20, ['*'], 'logbook_page')
                    ->withQueryString(),
        ]);
    }

    public function show(Request $request, PkpaRotationRun $run): View
    {
        abort_unless(PkpaRotationRun::query()
            ->whereKey($run->id)
            ->forSupervisor('internal', $request->user()->core_user_id)
            ->exists(), 403);

        $run->load(['practiceDomain', 'practiceSite', 'enrollment', 'progressSnapshots' => fn ($query) => $query->latest('snapshot_date')->limit(1)]);
        $selectedLogbook = $request->integer('logbook')
            ? $run->logbookEntries()->with(['attachments', 'reviews'])->whereKey($request->integer('logbook'))->where('status', '!=', 'draft')->firstOrFail()
            : null;
        $nextReadyLogbook = $selectedLogbook
            ? $run->logbookEntries()
                ->whereKeyNot($selectedLogbook->id)
                ->whereIn('status', PkpaLogbookEntry::internalReviewStatuses())
                ->oldest('entry_date')
                ->first()
            : null;
        $attendanceCount = $run->attendanceRecords()->where('submission_status', '!=', 'draft')->count();
        $logbookCount = $run->logbookEntries()->where('status', '!=', 'draft')->count();
        $waitingFieldCount = config('my_pkpa.preceptor_document_validation_enabled') ? $run->logbookEntries()->where('status', 'submitted')->count() : 0;
        $readyCount = $run->logbookEntries()->whereIn('status', PkpaLogbookEntry::internalReviewStatuses())->count();
        $completedCount = $run->logbookEntries()->where('status', 'internal_approved')->count();
        $view = in_array($request->query('view'), ['ready', 'logbooks', 'attendance'], true)
            ? $request->query('view')
            : ($readyCount > 0 ? 'ready' : 'logbooks');
        $status = in_array($request->query('status'), ['all', 'waiting', 'ready', 'completed', 'revision'], true)
            ? $request->query('status')
            : 'all';
        $search = trim((string) $request->query('q'));
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => array_merge(['nullable', 'date'], $request->filled('date_from') ? ['after_or_equal:date_from'] : [])]);
        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();

        $logbooks = $run->logbookEntries()
            ->where('status', '!=', 'draft')
            ->when($view === 'ready', fn ($query) => $query->whereIn('status', PkpaLogbookEntry::internalReviewStatuses()))
            ->when($view === 'logbooks' && $status === 'waiting', fn ($query) => $query->where('status', 'submitted'))
            ->when($view === 'logbooks' && $status === 'ready', fn ($query) => $query->whereIn('status', PkpaLogbookEntry::internalReviewStatuses()))
            ->when($view === 'logbooks' && $status === 'completed', fn ($query) => $query->where('status', 'internal_approved'))
            ->when($view === 'logbooks' && $status === 'revision', fn ($query) => $query->whereIn('status', ['revision_requested', 'rejected']))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhere('activity_summary', 'like', "%{$search}%")
                    ->orWhere('learning_outcomes', 'like', "%{$search}%");
            }))
            ->when($dateFrom, fn ($query) => $query->whereDate('entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('entry_date', '<=', $dateTo))
            ->latest('entry_date')
            ->paginate(12, ['*'], 'logbook_page')
            ->withQueryString();

        $attendances = $run->attendanceRecords()
            ->where('submission_status', '!=', 'draft')
            ->when($dateFrom, fn ($query) => $query->whereDate('attendance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('attendance_date', '<=', $dateTo))
            ->latest('attendance_date')
            ->paginate(12, ['*'], 'attendance_page')
            ->withQueryString();

        return view('internal-supervisor.pkpa-operations.show', [
            'run' => $run,
            'selectedLogbook' => $selectedLogbook,
            'nextReadyLogbook' => $nextReadyLogbook,
            'attendances' => $attendances,
            'logbooks' => $logbooks,
            'attendanceCount' => $attendanceCount,
            'logbookCount' => $logbookCount,
            'waitingFieldCount' => $waitingFieldCount,
            'readyCount' => $readyCount,
            'completedCount' => $completedCount,
            'view' => $view,
            'status' => $status,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function reviewLogbook(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approved,revision_requested,rejected'],
            'comments' => ['nullable', 'string', 'max:1500'],
        ]);
        $this->logbooks->internalReview($entry, $data['action'], $data['comments'] ?? null, $request->user());

        return redirect()->route('internal-supervisor.pkpa-operations.show', [
            'run' => $entry->pkpa_rotation_run_id,
            'view' => 'ready',
            'logbook' => $entry->id,
        ])->with('status', $data['action'] === 'approved'
            ? 'Validasi final tersimpan. Logbook ini sudah dikunci dan tidak dapat divalidasi ulang.'
            : 'Keputusan tersimpan. Mahasiswa dapat melihat catatan tindak lanjut.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'comments' => ['nullable', 'string', 'max:1500'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $entries = PkpaLogbookEntry::whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
            if ($entries->count() !== count($data['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Sebagian logbook tidak tersedia. Muat ulang antrean.']);
            }
            foreach ($entries as $entry) {
                $this->logbooks->internalReview($entry, 'approved', $data['comments'] ?? null, $request->user());
            }
        });

        return back()->with('status', count($data['ids']).' logbook berhasil divalidasi dan dikunci.');
    }

    public function downloadAttachment(Request $request, PkpaLogbookAttachment $attachment)
    {
        return $this->logbooks->downloadResponse($attachment, $request->user());
    }
}

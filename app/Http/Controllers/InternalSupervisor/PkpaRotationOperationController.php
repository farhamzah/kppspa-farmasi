<?php

namespace App\Http\Controllers\InternalSupervisor;

use App\Http\Controllers\Controller;
use App\Models\PkpaLogbookAttachment;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationRun;
use App\Services\PkpaLogbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PkpaRotationOperationController extends Controller
{
    public function __construct(private readonly PkpaLogbookService $logbooks)
    {
    }

    public function index(Request $request): View
    {
        return view('internal-supervisor.pkpa-operations.index', [
            'runs' => PkpaRotationRun::forSupervisor('internal', $request->user()->core_user_id)
                ->with(['practiceDomain', 'practiceSite', 'enrollment', 'logbookEntries'])
                ->latest()
                ->get(),
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

        return view('internal-supervisor.pkpa-operations.show', [
            'run' => $run,
            'selectedLogbook' => $selectedLogbook,
            'attendances' => $run->attendanceRecords()->where('submission_status', '!=', 'draft')->latest('attendance_date')->paginate(15, ['*'], 'attendance_page')->withQueryString(),
            'logbooks' => $run->logbookEntries()->where('status', '!=', 'draft')->latest('entry_date')->paginate(15, ['*'], 'logbook_page')->withQueryString(),
            'attendanceCount' => $run->attendanceRecords()->where('submission_status', '!=', 'draft')->count(),
            'waitingFieldCount' => $run->logbookEntries()->where('status', 'submitted')->count(),
            'readyCount' => $run->logbookEntries()->whereIn('status', ['field_approved', 'approved'])->count(),
        ]);
    }

    public function reviewLogbook(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approved,revision_requested,rejected'],
            'comments' => ['nullable', 'string', 'max:1500'],
        ]);
        $this->logbooks->internalReview($entry, $data['action'], $data['comments'] ?? null, $request->user());

        return back()->with('status', 'Validasi pembimbing dalam tersimpan.');
    }

    public function downloadAttachment(Request $request, PkpaLogbookAttachment $attachment)
    {
        return $this->logbooks->downloadResponse($attachment, $request->user());
    }
}

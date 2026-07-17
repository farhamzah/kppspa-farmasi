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
                ->with(['practiceDomain', 'practiceSite', 'logbookEntries'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, PkpaRotationRun $run): View
    {
        abort_unless($run->supervisorHistories()->where('supervisor_type', 'internal')->where('core_user_id', $request->user()->core_user_id)->where('status', 'active')->exists(), 403);

        return view('internal-supervisor.pkpa-operations.show', ['run' => $run->load(['practiceDomain', 'practiceSite', 'attendanceRecords', 'logbookEntries.attachments', 'logbookEntries.reviews'])]);
    }

    public function reviewLogbook(Request $request, PkpaLogbookEntry $entry): RedirectResponse
    {
        $data = $request->validate(['comments' => ['required', 'string', 'max:1500']]);
        $this->logbooks->internalReview($entry, $data['comments'], $request->user());

        return back()->with('status', 'Catatan monitoring logbook tersimpan.');
    }

    public function downloadAttachment(Request $request, PkpaLogbookAttachment $attachment)
    {
        return $this->logbooks->downloadResponse($attachment, $request->user());
    }
}

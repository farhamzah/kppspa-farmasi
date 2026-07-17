<?php

namespace App\Http\Controllers\Management;

use App\Exports\PkpaOfficialScheduleExport;
use App\Http\Controllers\Controller;
use App\Models\PkpaNotificationDelivery;
use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaProgram;
use App\Models\PkpaPublishedAssignment;
use App\Services\PkpaPlacementChangeRequestService;
use App\Services\PkpaPlacementNotificationService;
use App\Services\PkpaPlacementPlanService;
use App\Services\PkpaPlacementPublicationService;
use App\Services\PkpaPlacementReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PkpaPlacementPublicationController extends Controller
{
    public function __construct(
        private readonly PkpaPlacementReviewService $reviewService,
        private readonly PkpaPlacementPlanService $planService,
        private readonly PkpaPlacementPublicationService $publicationService,
        private readonly PkpaPlacementNotificationService $notificationService,
        private readonly PkpaPlacementChangeRequestService $changeService,
    ) {
    }

    public function index(Request $request): View
    {
        $program = PkpaProgram::query()
            ->when($request->filled('program_id'), fn ($query) => $query->whereKey($request->program_id))
            ->latest()
            ->first();
        $plan = $program?->placementPlans()->with('program')->whereIn('status', ['validated', 'locked'])->latest('version_number')->first();
        $review = $plan ? $this->reviewService->review($plan, $request->user(), false) : null;

        return view('management.pkpa-publications.index', [
            'programs' => PkpaProgram::latest()->get(),
            'program' => $program,
            'plan' => $plan,
            'review' => $review,
            'publications' => $program?->placementPublications()->withCount('assignments')->get() ?? collect(),
            'notifications' => PkpaNotificationDelivery::latest()->limit(15)->get(),
            'changeRequests' => $program ? PkpaPlacementChangeRequest::where('pkpa_program_id', $program->id)->latest()->get() : collect(),
        ]);
    }

    public function review(Request $request, PkpaPlacementPlan $plan): View
    {
        return view('management.pkpa-publications.review', [
            'plan' => $plan->load('program'),
            'review' => $this->reviewService->review($plan, $request->user(), true),
        ]);
    }

    public function lock(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp')) {
            abort(403);
        }
        if ($plan->status !== 'locked') {
            $plan->update(['status' => 'locked', 'updated_by_core_user_id' => $request->user()->core_user_id]);
        }

        return back()->with('status', 'Placement plan dikunci untuk publikasi.');
    }

    public function publish(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp')) {
            abort(403);
        }
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'confirmation' => ['required', 'string'],
            'effective_at' => ['nullable', 'date'],
        ]);
        $publication = $this->publicationService->publish($plan, $data, $request->user());
        $this->notificationService->sendPending($request->user());

        return redirect()->route('management.pkpa-publications.show', $publication)->with('status', 'Jadwal PKPA berhasil dipublikasikan.');
    }

    public function show(PkpaPlacementPublication $publication): View
    {
        return view('management.pkpa-publications.show', [
            'publication' => $publication->load(['program', 'plan', 'assignments.supervisors']),
            'acks' => $publication->acknowledgements()->get(),
            'notifications' => PkpaNotificationDelivery::where('entity_type', PkpaPlacementPublication::class)->where('entity_id', $publication->id)->latest()->get(),
        ]);
    }

    public function withdraw(Request $request, PkpaPlacementPublication $publication): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp')) {
            abort(403);
        }
        $data = $request->validate(['withdrawal_reason' => ['required', 'string']]);
        $this->publicationService->withdraw($publication, $data['withdrawal_reason'], $request->user());
        $this->notificationService->sendPending($request->user());

        return back()->with('status', 'Publication ditarik dan notification dicatat.');
    }

    public function export(PkpaPlacementPublication $publication): BinaryFileResponse
    {
        return Excel::download(new PkpaOfficialScheduleExport($publication), 'jadwal_resmi_pkpa_'.$publication->code.'.xlsx');
    }

    public function retryNotifications(Request $request): RedirectResponse
    {
        $result = $this->notificationService->sendPending($request->user());

        return back()->with('status', "Notifikasi diproses: {$result['sent']} terkirim, {$result['skipped']} skipped, {$result['failed']} gagal.");
    }

    public function createChange(PkpaPlacementPublication $publication): View
    {
        return view('management.pkpa-publications.change-create', [
            'publication' => $publication->load('assignments.supervisors'),
        ]);
    }

    public function storeChange(Request $request, PkpaPlacementPublication $publication): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
            'request_type' => ['required', 'string'],
            'assignment_id' => ['required', 'exists:pkpa_published_assignments,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $change = $this->changeService->create($publication, $data, $request->user());
        $assignment = PkpaPublishedAssignment::findOrFail($data['assignment_id']);
        $this->changeService->addItem($change, $assignment, array_filter([
            'change_type' => $data['request_type'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]), $request->user());

        return redirect()->route('management.pkpa-change-requests.show', $change)->with('status', 'Change request dibuat.');
    }

    public function showChange(PkpaPlacementChangeRequest $changeRequest): View
    {
        return view('management.pkpa-publications.change-show', [
            'change' => $changeRequest->load(['publication', 'items.oldAssignment.supervisors']),
        ]);
    }

    public function submitChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $this->changeService->submit($changeRequest, $request->user());

        return back()->with('status', 'Change request diajukan untuk review.');
    }

    public function approveChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $this->changeService->approve($changeRequest, $request->user());

        return back()->with('status', 'Change request disetujui.');
    }

    public function rejectChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string']]);
        $this->changeService->reject($changeRequest, $data['rejection_reason'], $request->user());

        return back()->with('status', 'Change request ditolak.');
    }

    public function applyChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $publication = $this->changeService->apply($changeRequest, $request->user());
        $this->notificationService->sendPending($request->user());

        return redirect()->route('management.pkpa-publications.show', $publication)->with('status', 'Revisi publication diterapkan.');
    }
}

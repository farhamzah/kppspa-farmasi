<?php

namespace App\Http\Controllers\Management;

use App\Exports\PkpaOfficialScheduleExport;
use App\Http\Controllers\Controller;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaNotificationDelivery;
use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaProgram;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaSiteFieldSupervisor;
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
    ) {}

    public function index(Request $request): View
    {
        $program = PkpaProgram::query()
            ->when($request->filled('program_id'), fn ($query) => $query->whereKey($request->program_id))
            ->latest()
            ->first();
        $plan = $program?->placementPlans()
            ->with('program')
            ->current()
            ->latest('version_number')
            ->first()
            ?? $program?->placementPlans()
                ->with('program')
                ->latest('version_number')
                ->first();
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
        $publication = $this->publicationService->syncLockedPlanToPortal($plan, $request->user());

        return back()->with('status', 'Rancangan dikunci dan '.$publication->assignments()->count().' assignment valid langsung ditampilkan ke portal.');
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

        return back()->with('status', 'Publikasi ditarik dan notifikasi dicatat.');
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
        $publication->load('assignments.supervisors');

        return view('management.pkpa-publications.change-create', [
            'publication' => $publication,
            'fieldSupervisors' => PkpaSiteFieldSupervisor::query()
                ->with('practiceSite')
                ->whereIn('practice_site_id', $publication->assignments->pluck('practice_site_id')->filter()->unique())
                ->where('status', 'active')
                ->orderBy('name_snapshot')
                ->get(),
        ]);
    }

    public function createInternalSupervisorReplacement(Request $request, PkpaPlacementPublication $publication): View
    {
        if (! $request->user()->hasAnyRole(['admin', 'koordinator_kp'])) {
            abort(403);
        }
        abort_unless($publication->is_current && $publication->status === 'published', 404);

        $publication->load(['assignments.supervisors', 'assignments.practiceDomain']);
        $internalSupervisors = PkpaInternalSupervisorEligibility::query()
            ->with('practiceDomain')
            ->where('pkpa_program_id', $publication->pkpa_program_id)
            ->where('status', 'active')
            ->orderBy('name_snapshot')
            ->get()
            ->groupBy('core_user_id')
            ->map(fn ($items) => [
                'core_user_id' => (string) $items->first()->core_user_id,
                'name' => $items->first()->display_name,
                'domains' => $items->pluck('practiceDomain.name')->filter()->unique()->values()->all(),
            ])
            ->values();

        return view('management.pkpa-publications.internal-supervisor-replacement', compact('publication', 'internalSupervisors'));
    }

    public function storeInternalSupervisorReplacement(Request $request, PkpaPlacementPublication $publication): RedirectResponse
    {
        $data = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'distinct', 'exists:pkpa_published_assignments,id'],
            'replacement_core_user_id' => ['required', 'string', 'max:80'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:10'],
            'confirmation' => ['accepted'],
        ]);
        $change = $this->changeService->createInternalSupervisorReplacement(
            $publication,
            $data['assignment_ids'],
            $data['replacement_core_user_id'],
            $data['effective_date'],
            $data['reason'],
            $request->user(),
        );

        return redirect()->route('management.pkpa-change-requests.show', $change)->with('status', 'Rancangan penggantian dibuat. Periksa ringkasan sebelum diterapkan.');
    }

    public function storeChange(Request $request, PkpaPlacementPublication $publication): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
            'request_type' => ['required', 'string'],
            'assignment_id' => ['required', 'exists:pkpa_published_assignments,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'site_field_supervisor_id' => ['nullable', 'required_if:request_type,supervisor_change', 'exists:pkpa_site_field_supervisors,id'],
            'notes' => ['nullable', 'string'],
        ]);
        $change = $this->changeService->create($publication, $data, $request->user());
        $assignment = PkpaPublishedAssignment::findOrFail($data['assignment_id']);
        $this->changeService->addItem($change, $assignment, array_filter([
            'change_type' => $data['request_type'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'site_field_supervisor_id' => $data['site_field_supervisor_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]), $request->user());

        return redirect()->route('management.pkpa-change-requests.show', $change)->with('status', 'Permintaan perubahan dibuat.');
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

        return back()->with('status', 'Permintaan perubahan diajukan untuk pemeriksaan.');
    }

    public function approveChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $this->changeService->approve($changeRequest, $request->user());

        return back()->with('status', 'Permintaan perubahan disetujui.');
    }

    public function rejectChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string']]);
        $this->changeService->reject($changeRequest, $data['rejection_reason'], $request->user());

        return back()->with('status', 'Permintaan perubahan ditolak.');
    }

    public function applyChange(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        $publication = $this->changeService->apply($changeRequest, $request->user());
        $this->notificationService->sendPending($request->user());

        return redirect()->route('management.pkpa-publications.show', $publication)->with('status', 'Revisi publikasi diterapkan.');
    }

    public function confirmInternalSupervisorReplacement(Request $request, PkpaPlacementChangeRequest $changeRequest): RedirectResponse
    {
        if (! $request->user()->hasRole('koordinator_kp') || $changeRequest->request_type !== 'internal_supervisor_replacement') {
            abort(403);
        }
        if ($changeRequest->status === 'draft') {
            $this->changeService->submit($changeRequest, $request->user());
        }
        if (in_array($changeRequest->fresh()->status, ['submitted', 'under_review'], true)) {
            $this->changeService->approve($changeRequest->fresh(), $request->user());
        }
        $publication = $this->changeService->apply($changeRequest->fresh(), $request->user());
        $this->notificationService->sendPending($request->user());

        return redirect()->route('management.pkpa-publications.show', $publication)->with('status', 'Pembimbing Dalam berhasil diganti. Riwayat lama tetap tersimpan dan portal telah disinkronkan.');
    }
}

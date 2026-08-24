<?php

namespace App\Http\Controllers\Management;

use App\Exports\PkpaPlacementPlannerExport;
use App\Http\Controllers\Controller;
use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementActionBatch;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\PkpaRotationAssignment;
use App\Models\PkpaSiteFieldSupervisor;
use App\Services\PkpaPlacementBulkActionService;
use App\Services\PkpaPlacementCapacityService;
use App\Services\PkpaPlacementPlanService;
use App\Services\PkpaPlacementTimelineService;
use App\Services\PkpaPlacementValidationService;
use App\Services\PkpaRotationAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PkpaPlacementPlannerController extends Controller
{
    public function __construct(
        private readonly PkpaPlacementPlanService $planService,
        private readonly PkpaRotationAssignmentService $assignmentService,
        private readonly PkpaPlacementBulkActionService $bulkService,
        private readonly PkpaPlacementValidationService $validationService,
        private readonly PkpaPlacementTimelineService $timelineService,
        private readonly PkpaPlacementCapacityService $capacityService,
    ) {
    }

    public function index(Request $request): View
    {
        $program = PkpaProgram::query()
            ->when($request->filled('program_id'), fn ($query) => $query->whereKey($request->program_id))
            ->orderByDesc('id')
            ->first();
        $plan = $program ? $this->selectedPlan($request, $program) : null;

        return view('management.pkpa-placement-planner.index', [
            'programs' => PkpaProgram::orderByDesc('id')->get(),
            'program' => $program,
            'plans' => $program?->placementPlans()->withCount('assignments')->get() ?? collect(),
            'plan' => $plan,
            'progress' => $plan ? $this->planService->progress($plan->loadMissing('program')) : null,
            'domains' => $program?->domains()->with('practiceDomain')->where('is_active', true)->orderBy('sort_order')->get() ?? collect(),
            'enrollments' => $program ? $this->enrollments($request, $program, $plan) : collect(),
            'programSites' => $program?->programSites()->with(['practiceSite.fieldSupervisors', 'availabilityPeriods', 'practiceDomain', 'practiceDomainOption'])->where('is_active', true)->whereIn('status', ['ready', 'active'])->get() ?? collect(),
            'internalSupervisors' => $program?->internalSupervisorEligibilities()->where('status', 'active')->get() ?? collect(),
            'fieldSupervisors' => PkpaSiteFieldSupervisor::query()->where('status', 'active')->get(),
            'latestRun' => $plan?->validationRuns()->with('issues.assignment.enrollment', 'issues.assignment.practiceDomain')->latest()->first(),
            'latestBatch' => $plan?->actionBatches()->with('items')->latest()->first(),
            'filters' => $request->only(['program_id', 'plan_id', 'q', 'group_id', 'domain_id', 'status', 'empty']),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pkpa_program_id' => ['required', 'exists:pkpa_programs,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $program = PkpaProgram::findOrFail($data['pkpa_program_id']);
        $plan = $this->planService->create($program, $data, $request->user());

        return redirect()->route('management.pkpa-placement-planner.index', ['program_id' => $program->id, 'plan_id' => $plan->id])->with('status', 'Rancangan penempatan dibuat.');
    }

    public function clonePlan(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'copy_assignments' => ['nullable', 'boolean'],
        ]);
        $newPlan = $this->planService->clone($plan, $data + ['copy_assignments' => $request->boolean('copy_assignments', true)], $request->user());

        return redirect()->route('management.pkpa-placement-planner.index', ['program_id' => $newPlan->pkpa_program_id, 'plan_id' => $newPlan->id])->with('status', 'Rancangan berhasil dikloning.');
    }

    public function setCurrent(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        $this->planService->setCurrent($plan, $request->user());

        return back()->with('status', 'Rancangan current diperbarui.');
    }

    public function lock(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        $this->planService->lock($plan, $request->user());

        return back()->with('status', 'Rancangan dikunci.');
    }

    public function saveAssignment(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'pkpa_enrollment_requirement_id' => ['required', 'exists:pkpa_enrollment_requirements,id'],
            'pkpa_program_site_id' => ['required', 'exists:pkpa_program_sites,id'],
            'pkpa_site_availability_period_id' => ['required', 'exists:pkpa_site_availability_periods,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'internal_supervisor_eligibility_id' => ['required', 'exists:pkpa_internal_supervisor_eligibilities,id'],
            'site_field_supervisor_id' => ['required', 'exists:pkpa_site_field_supervisors,id'],
            'row_version' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);
        $requirement = PkpaEnrollmentRequirement::findOrFail($data['pkpa_enrollment_requirement_id']);
        $this->assignmentService->save($plan, $requirement, $data, $request->user());

        return back()->with('status', 'Penempatan disimpan.');
    }

    public function deleteAssignment(Request $request, PkpaRotationAssignment $assignment): RedirectResponse
    {
        $this->assignmentService->deleteDraft($assignment, $request->user());

        return back()->with('status', 'Penempatan draft dihapus.');
    }

    public function bulkPreview(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'practice_domain_id' => ['required', 'exists:pkpa_practice_domains,id'],
            'pkpa_program_site_id' => ['required', 'exists:pkpa_program_sites,id'],
            'pkpa_site_availability_period_id' => ['required', 'exists:pkpa_site_availability_periods,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'internal_supervisor_eligibility_id' => ['required', 'exists:pkpa_internal_supervisor_eligibilities,id'],
            'site_field_supervisor_id' => ['required', 'exists:pkpa_site_field_supervisors,id'],
            'enrollment_ids' => ['nullable', 'array'],
            'enrollment_ids.*' => ['integer', 'exists:pkpa_enrollments,id'],
            'student_group_id' => ['nullable', 'exists:pkpa_student_groups,id'],
            'overwrite_mode' => ['nullable', 'in:empty_only,overwrite_draft'],
        ]);
        $batch = $this->bulkService->preview($plan, $data + ['action_type' => 'assign_complete'], $request->user());

        return back()->with('status', 'Preview bulk dibuat: '.$batch->items->where('result_status', 'valid')->count().' siap, '.$batch->items->where('result_status', 'invalid')->count().' gagal.');
    }

    public function bulkApply(Request $request, PkpaPlacementActionBatch $batch): RedirectResponse
    {
        $this->bulkService->apply($batch, $request->user(), $request->boolean('valid_only'));

        return back()->with('status', 'Bulk action diterapkan.');
    }

    public function bulkUndo(Request $request, PkpaPlacementActionBatch $batch): RedirectResponse
    {
        $this->bulkService->undo($batch, $request->user());

        return back()->with('status', 'Bulk action dibatalkan secara aman.');
    }

    public function validatePlan(Request $request, PkpaPlacementPlan $plan): RedirectResponse
    {
        $run = $this->validationService->validatePlan($plan, $request->user());

        return back()->with('status', "Validasi selesai: {$run->error_count} error, {$run->warning_count} peringatan.");
    }

    public function timeline(Request $request, PkpaPlacementPlan $plan): View
    {
        return view('management.pkpa-placement-planner.timeline', [
            'plan' => $plan->load('program'),
            'timeline' => $this->timelineService->build($plan),
        ]);
    }

    public function export(PkpaPlacementPlan $plan): BinaryFileResponse
    {
        return Excel::download(new PkpaPlacementPlannerExport($plan), 'rancangan_internal_pkpa_'.$plan->code.'.xlsx');
    }

    public function options(Request $request, PkpaPlacementPlan $plan)
    {
        $domainId = $request->integer('practice_domain_id');
        $programSites = $plan->program->programSites()->with(['practiceSite', 'availabilityPeriods'])
            ->where('practice_domain_id', $domainId)
            ->where('is_active', true)
            ->whereIn('status', ['ready', 'active'])
            ->get()
            ->map(fn (PkpaProgramSite $site) => [
                'id' => $site->id,
                'practice_site_id' => $site->practice_site_id,
                'name' => $site->practiceSite?->name,
                'label' => trim(($site->practiceSite?->name ?? '').($site->practiceDomainOption ? ' / '.$site->practiceDomainOption->name : '')),
                'option_id' => $site->practice_domain_option_id,
                'availability' => $site->availabilityPeriods->map(fn ($period) => [
                    'id' => $period->id,
                    'label' => $period->start_date->format('d M Y').' - '.$period->end_date->format('d M Y'),
                    'capacity' => $this->capacityService->usage($plan, $period),
                    'start_date' => $period->start_date?->toDateString(),
                    'end_date' => $period->end_date?->toDateString(),
                ])->values(),
                'field_supervisors' => $site->practiceSite?->fieldSupervisors()
                    ->where('status', 'active')
                    ->get()
                    ->map(fn (PkpaSiteFieldSupervisor $supervisor) => [
                        'id' => $supervisor->id,
                        'practice_site_id' => $supervisor->practice_site_id,
                        'name' => $supervisor->name_snapshot,
                        'label' => trim(($supervisor->name_snapshot ?? '').($supervisor->position_title ? ' / '.$supervisor->position_title : '')),
                        'maximum_active_students' => $supervisor->maximum_active_students,
                    ])->values(),
            ]);
        $internal = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $plan->pkpa_program_id)->where('practice_domain_id', $domainId)->where('status', 'active')->get()
            ->map(fn (PkpaInternalSupervisorEligibility $supervisor) => [
                'id' => $supervisor->id,
                'name' => $supervisor->name_snapshot,
                'label' => trim(($supervisor->name_snapshot ?? '').' / max '.($supervisor->maximum_active_students ?? '?')),
                'maximum_active_students' => $supervisor->maximum_active_students,
            ])->values();

        return response()->json(['program_sites' => $programSites->values(), 'internal_supervisors' => $internal]);
    }

    private function selectedPlan(Request $request, PkpaProgram $program): ?PkpaPlacementPlan
    {
        return $program->placementPlans()
            ->with(['assignments.supervisors', 'program'])
            ->when($request->filled('plan_id'), fn ($query) => $query->whereKey($request->plan_id))
            ->first()
            ?? $program->placementPlans()->with(['assignments.supervisors', 'program'])->current()->first();
    }

    private function enrollments(Request $request, PkpaProgram $program, ?PkpaPlacementPlan $plan)
    {
        return $program->enrollments()
            ->with(['requirements.practiceDomain', 'requirements.selectedOption', 'activeGroupMembership.group', 'rotationAssignments' => fn ($query) => $query->where('pkpa_placement_plan_id', $plan?->id ?? 0)->with(['practiceSite', 'selectedOption', 'supervisors'])])
            ->whereIn('status', ['active', 'on_hold', 'cancelled'])
            ->search($request->input('q'))
            ->when($request->filled('group_id'), fn ($query) => $query->whereHas('activeGroupMembership', fn ($group) => $group->where('pkpa_student_group_id', $request->group_id)))
            ->orderBy('student_number')
            ->paginate((int) $request->input('per_page', 25))
            ->withQueryString();
    }
}

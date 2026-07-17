<?php

namespace App\Services;

use App\Models\PkpaPlacementPlan;
use App\Models\PkpaProgram;
use App\Models\PkpaRotationAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPlacementPlanService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function create(PkpaProgram $program, array $data, ?User $actor): PkpaPlacementPlan
    {
        return DB::transaction(function () use ($program, $data, $actor) {
            $version = ((int) $program->placementPlans()->lockForUpdate()->max('version_number')) + 1;
            $plan = PkpaPlacementPlan::create([
                'pkpa_program_id' => $program->id,
                'code' => $data['code'] ?? $program->code.'-PLAN-V'.$version,
                'name' => $data['name'] ?? 'Rancangan Penempatan v'.$version,
                'version_number' => $version,
                'status' => 'draft',
                'description' => $data['description'] ?? null,
                'validation_status' => 'not_validated',
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);

            if (! $program->placementPlans()->where('id', '!=', $plan->id)->where('is_current', true)->exists()) {
                $this->setCurrent($plan, $actor);
            }

            $this->audit->record($actor, 'placement_plan_created', $plan, null, $plan->only(['code', 'version_number', 'status']));

            return $plan->refresh();
        });
    }

    public function clone(PkpaPlacementPlan $source, array $data, ?User $actor): PkpaPlacementPlan
    {
        return DB::transaction(function () use ($source, $data, $actor) {
            $source->loadMissing('program');
            $plan = $this->create($source->program, [
                'code' => $data['code'] ?? $source->program->code.'-PLAN-V'.(((int) $source->program->placementPlans()->max('version_number')) + 1),
                'name' => $data['name'] ?? $source->name.' (Revisi)',
                'description' => $data['description'] ?? 'Kloning dari '.$source->code,
            ], $actor);

            if (($data['copy_assignments'] ?? true) !== false) {
                $source->assignments()->with('supervisors')->get()->each(function (PkpaRotationAssignment $assignment) use ($plan, $actor) {
                    $copy = $assignment->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                    $copy->pkpa_placement_plan_id = $plan->id;
                    $copy->planning_source = 'copied';
                    $copy->validation_status = 'not_validated';
                    $copy->last_validated_at = null;
                    $copy->status = $assignment->status === 'valid' ? 'draft' : $assignment->status;
                    $copy->row_version = 1;
                    $copy->created_by_core_user_id = $actor?->core_user_id;
                    $copy->updated_by_core_user_id = $actor?->core_user_id;
                    $copy->save();

                    foreach ($assignment->supervisors as $supervisor) {
                        $supervisorCopy = $supervisor->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                        $supervisorCopy->pkpa_rotation_assignment_id = $copy->id;
                        $supervisorCopy->created_by_core_user_id = $actor?->core_user_id;
                        $supervisorCopy->updated_by_core_user_id = $actor?->core_user_id;
                        $supervisorCopy->save();
                    }
                });
            }

            $this->audit->record($actor, 'placement_plan_cloned', $plan, ['source_plan_id' => $source->id], $plan->only(['code', 'version_number']));

            return $plan->refresh();
        });
    }

    public function setCurrent(PkpaPlacementPlan $plan, ?User $actor): PkpaPlacementPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            PkpaPlacementPlan::where('pkpa_program_id', $plan->pkpa_program_id)
                ->lockForUpdate()
                ->update(['is_current' => false, 'current_key' => null]);

            $plan->update([
                'is_current' => true,
                'current_key' => 'PROGRAM:'.$plan->pkpa_program_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'placement_plan_set_current', $plan, null, ['current_key' => $plan->current_key]);

            return $plan->refresh();
        });
    }

    public function lock(PkpaPlacementPlan $plan, ?User $actor): PkpaPlacementPlan
    {
        if ($plan->validation_status !== 'valid') {
            throw ValidationException::withMessages(['plan' => 'Rancangan harus tervalidasi tanpa error sebelum dikunci.']);
        }

        $old = $plan->only(['status']);
        $plan->update(['status' => 'locked', 'updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'placement_plan_locked', $plan, $old, ['status' => 'locked']);

        return $plan->refresh();
    }

    public function archive(PkpaPlacementPlan $plan, ?User $actor): PkpaPlacementPlan
    {
        $plan->update([
            'status' => 'archived',
            'is_current' => false,
            'current_key' => null,
            'archived_at' => now(),
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
        $this->audit->record($actor, 'placement_plan_archived', $plan, null, ['status' => 'archived']);

        return $plan->refresh();
    }

    public function markStale(PkpaPlacementPlan $plan, ?User $actor): void
    {
        if ($plan->status === 'validated' || $plan->validation_status === 'valid') {
            $plan->update([
                'status' => 'needs_revision',
                'validation_status' => 'stale',
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
        }
    }

    public function progress(PkpaPlacementPlan $plan): array
    {
        $required = $plan->program?->enrollments()->where('status', 'active')->withCount('requirements')->get()->sum('requirements_count') ?? 0;
        $filled = $plan->assignments()->whereNotIn('status', ['cancelled', 'superseded'])->count();
        $valid = $plan->assignments()->where('status', 'valid')->count();

        return [
            'required' => $required,
            'filled' => $filled,
            'empty' => max(0, $required - $filled),
            'valid' => $valid,
            'percent' => $required > 0 ? (int) floor(($filled / $required) * 100) : 0,
        ];
    }
}

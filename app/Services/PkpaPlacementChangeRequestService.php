<?php

namespace App\Services;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPlacementChangeRequestItem;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationAssignmentSupervisor;
use App\Models\PkpaRotationRun;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPlacementChangeRequestService
{
    public function __construct(
        private readonly PkpaPlacementPublicationService $publicationService,
        private readonly PkpaRotationPublicationSyncService $rotationSyncService,
        private readonly PkpaAuditService $audit,
    ) {}

    public function createInternalSupervisorReplacement(
        PkpaPlacementPublication $publication,
        array $assignmentIds,
        string $replacementCoreUserId,
        string $requestedEffectiveDate,
        string $reason,
        ?User $actor,
    ): PkpaPlacementChangeRequest {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat mengganti Pembimbing Dalam.']);
        }
        if (! $publication->is_current || $publication->status !== 'published') {
            throw ValidationException::withMessages(['publication' => 'Penggantian hanya dapat dilakukan pada publikasi resmi terkini.']);
        }

        return DB::transaction(function () use ($publication, $assignmentIds, $replacementCoreUserId, $requestedEffectiveDate, $reason, $actor) {
            $assignments = $publication->assignments()
                ->with(['supervisors', 'practiceDomain'])
                ->whereIn('id', array_values(array_unique($assignmentIds)))
                ->lockForUpdate()
                ->get();

            if ($assignments->count() !== count(array_unique($assignmentIds))) {
                throw ValidationException::withMessages(['assignment_ids' => 'Sebagian penempatan tidak berasal dari publikasi aktif.']);
            }

            $change = $this->create($publication, [
                'reason' => $reason,
                'request_type' => 'internal_supervisor_replacement',
            ], $actor);

            $domains = [];
            $oldSupervisors = [];
            $replacementNames = [];
            foreach ($assignments as $assignment) {
                $oldSupervisor = $assignment->supervisors->firstWhere('supervisor_type', 'internal');
                if (! $oldSupervisor) {
                    throw ValidationException::withMessages(['assignment_ids' => "{$assignment->student_name_snapshot} belum memiliki Pembimbing Dalam."]);
                }
                if ((string) $oldSupervisor->core_user_id === $replacementCoreUserId) {
                    throw ValidationException::withMessages(['replacement_core_user_id' => "{$assignment->student_name_snapshot} sudah dibimbing oleh dosen pengganti yang dipilih."]);
                }

                $run = PkpaRotationRun::where('pkpa_enrollment_requirement_id', $assignment->pkpa_enrollment_requirement_id)->first();
                $partialAssessment = $run && $run->rotationAssessment()
                    ->whereNotIn('status', ['submitted', 'finalized', 'locked'])
                    ->whereHas('assessors', fn ($query) => $query
                        ->where('assessor_type', 'internal_supervisor')
                        ->where('core_user_id', $oldSupervisor->core_user_id)
                        ->whereNull('submitted_at')
                        ->whereHas('scores'))
                    ->exists();
                if ($partialAssessment) {
                    throw ValidationException::withMessages([
                        'assignment_ids' => "Penilaian {$assignment->student_name_snapshot} sedang diisi pembimbing lama. Selesaikan atau batalkan penilaian tersebut sebelum penggantian.",
                    ]);
                }

                $eligibility = PkpaInternalSupervisorEligibility::query()
                    ->where('pkpa_program_id', $publication->pkpa_program_id)
                    ->where('practice_domain_id', $assignment->practice_domain_id)
                    ->where('core_user_id', $replacementCoreUserId)
                    ->where('status', 'active')
                    ->first();
                if (! $eligibility) {
                    throw ValidationException::withMessages([
                        'replacement_core_user_id' => "Dosen pengganti belum aktif untuk wahana {$assignment->practice_domain_name_snapshot}.",
                    ]);
                }

                $effectiveDate = Carbon::parse($requestedEffectiveDate)->max($assignment->start_date)->toDateString();
                if ($effectiveDate > $assignment->end_date->toDateString()) {
                    throw ValidationException::withMessages(['effective_date' => "Tanggal efektif melewati periode {$assignment->student_name_snapshot}."]);
                }

                $item = $this->addItem($change, $assignment, [
                    'change_type' => 'internal_supervisor_change',
                    'internal_supervisor_eligibility_id' => $eligibility->id,
                    'internal_supervisor_core_user_id' => $eligibility->core_user_id,
                    'internal_supervisor_name' => $eligibility->display_name,
                    'effective_date' => $effectiveDate,
                    'requested_effective_date' => $requestedEffectiveDate,
                    'notes' => $reason,
                ], $actor);
                if ($item->validation_status !== 'valid') {
                    throw ValidationException::withMessages(['assignment_ids' => $item->validation_messages['errors'][0] ?? 'Penempatan tidak valid untuk dialihkan.']);
                }

                $domains[$assignment->practice_domain_id] = $assignment->practice_domain_name_snapshot;
                $oldSupervisors[$oldSupervisor->core_user_id] = $oldSupervisor->display_name;
                $replacementNames[$eligibility->core_user_id] = $eligibility->display_name;
            }

            $change->update(['impact_summary' => [
                'affected_assignments' => $assignments->count(),
                'affected_students' => $assignments->pluck('student_core_user_id')->unique()->count(),
                'domains' => array_values($domains),
                'old_supervisors' => array_values($oldSupervisors),
                'replacement_core_user_id' => $replacementCoreUserId,
                'replacement_name' => array_values($replacementNames)[0] ?? null,
                'requested_effective_date' => $requestedEffectiveDate,
            ]]);

            return $change->fresh(['items.oldAssignment.supervisors']);
        });
    }

    public function create(PkpaPlacementPublication $publication, array $data, ?User $actor): PkpaPlacementChangeRequest
    {
        if (blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Alasan perubahan wajib diisi.']);
        }

        return DB::transaction(function () use ($publication, $data, $actor) {
            $number = 'CR-'.str_pad((string) (((int) PkpaPlacementChangeRequest::where('pkpa_program_id', $publication->pkpa_program_id)->lockForUpdate()->count()) + 1), 4, '0', STR_PAD_LEFT);
            $request = PkpaPlacementChangeRequest::create([
                'pkpa_program_id' => $publication->pkpa_program_id,
                'pkpa_placement_publication_id' => $publication->id,
                'request_number' => $number,
                'request_type' => $data['request_type'] ?? 'student_assignment_change',
                'status' => 'draft',
                'reason' => $data['reason'],
                'requested_by_core_user_id' => $actor?->core_user_id,
                'requested_at' => now(),
            ]);
            $this->audit->record($actor, 'placement_change_request_created', $request, null, $request->only(['request_number', 'request_type']));

            return $request;
        });
    }

    public function addItem(PkpaPlacementChangeRequest $request, PkpaPublishedAssignment $assignment, array $proposed, ?User $actor): PkpaPlacementChangeRequestItem
    {
        if ($assignment->pkpa_placement_publication_id !== $request->pkpa_placement_publication_id) {
            throw ValidationException::withMessages(['assignment' => 'Assignment tidak berasal dari publication request.']);
        }
        $messages = [];
        if (! empty($proposed['start_date']) && ! empty($proposed['end_date']) && $proposed['start_date'] > $proposed['end_date']) {
            $messages[] = 'Tanggal selesai harus setelah tanggal mulai.';
        }
        if (blank($proposed)) {
            $messages[] = 'Proposed change belum diisi.';
        }
        if (in_array(($proposed['change_type'] ?? null), ['supervisor_change', 'field_supervisor_change'], true)) {
            $fieldSupervisor = filled($proposed['site_field_supervisor_id'] ?? null)
                ? PkpaSiteFieldSupervisor::query()->whereKey($proposed['site_field_supervisor_id'])->where('status', 'active')->first()
                : null;

            if (! $fieldSupervisor) {
                $messages[] = 'Preseptor aktif wajib dipilih untuk perubahan pembimbing.';
            } elseif ($fieldSupervisor->practice_site_id !== $assignment->practice_site_id) {
                $messages[] = 'Preseptor harus berasal dari wahana mahasiswa yang dipilih.';
            }
        }
        if (($proposed['change_type'] ?? null) === 'internal_supervisor_change') {
            $internal = PkpaInternalSupervisorEligibility::query()
                ->whereKey($proposed['internal_supervisor_eligibility_id'] ?? null)
                ->where('pkpa_program_id', $request->pkpa_program_id)
                ->where('practice_domain_id', $assignment->practice_domain_id)
                ->where('status', 'active')
                ->first();
            if (! $internal) {
                $messages[] = 'Pembimbing Dalam pengganti tidak aktif untuk program dan wahana penempatan ini.';
            }
            if (blank($proposed['effective_date'] ?? null)) {
                $messages[] = 'Tanggal efektif penggantian wajib diisi.';
            }
        }

        $item = PkpaPlacementChangeRequestItem::create([
            'pkpa_placement_change_request_id' => $request->id,
            'old_published_assignment_id' => $assignment->id,
            'pkpa_enrollment_id' => $assignment->pkpa_enrollment_id,
            'pkpa_enrollment_requirement_id' => $assignment->pkpa_enrollment_requirement_id,
            'change_type' => $proposed['change_type'] ?? 'date_change',
            'before_snapshot' => $assignment->loadMissing('supervisors')->toArray(),
            'proposed_snapshot' => $proposed,
            'validation_status' => count($messages) ? 'error' : 'valid',
            'validation_messages' => ['errors' => $messages],
        ]);
        $this->audit->record($actor, 'placement_change_request_item_added', $item, null, ['validation_status' => $item->validation_status]);

        return $item;
    }

    public function submit(PkpaPlacementChangeRequest $request, ?User $actor): PkpaPlacementChangeRequest
    {
        if ($request->items()->where('validation_status', 'error')->exists() || ! $request->items()->exists()) {
            throw ValidationException::withMessages(['request' => 'Request belum valid atau belum memiliki item.']);
        }
        $request->update(['status' => 'submitted', 'reviewed_by_core_user_id' => $actor?->core_user_id, 'reviewed_at' => now()]);
        $this->audit->record($actor, 'placement_change_request_submitted', $request, null, ['status' => 'submitted']);

        return $request->refresh();
    }

    public function approve(PkpaPlacementChangeRequest $request, ?User $actor): PkpaPlacementChangeRequest
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Koordinator PKPA yang dapat approve request.']);
        }
        if (! in_array($request->status, ['submitted', 'under_review'], true)) {
            throw ValidationException::withMessages(['request' => 'Request belum siap di-approve.']);
        }
        $request->update(['status' => 'approved', 'approved_by_core_user_id' => $actor?->core_user_id, 'approved_at' => now()]);
        $this->audit->record($actor, 'placement_change_request_approved', $request, null, ['status' => 'approved']);

        return $request->refresh();
    }

    public function reject(PkpaPlacementChangeRequest $request, string $reason, ?User $actor): PkpaPlacementChangeRequest
    {
        $request->update(['status' => 'rejected', 'rejected_by_core_user_id' => $actor?->core_user_id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
        $this->audit->record($actor, 'placement_change_request_rejected', $request, null, ['reason' => $reason]);

        return $request->refresh();
    }

    public function apply(PkpaPlacementChangeRequest $request, ?User $actor): PkpaPlacementPublication
    {
        if ($request->status === 'applied') {
            throw ValidationException::withMessages(['request' => 'Request sudah pernah diterapkan.']);
        }
        if ($request->status !== 'approved') {
            throw ValidationException::withMessages(['request' => 'Request harus approved sebelum apply.']);
        }

        $replacement = [];
        $supervisorContexts = [];
        foreach ($request->items()->with('oldAssignment.supervisors')->get() as $item) {
            $snapshot = $item->oldAssignment->toArray();
            foreach (($item->proposed_snapshot ?? []) as $key => $value) {
                if (array_key_exists($key, $snapshot) || in_array($key, ['start_date', 'end_date', 'notes', 'site_field_supervisor_id', 'internal_supervisor_eligibility_id', 'effective_date'], true)) {
                    $snapshot[$key] = $value;
                }
            }
            $replacement[$item->old_published_assignment_id] = $snapshot;
            if ($item->change_type === 'internal_supervisor_change') {
                $supervisorContexts[$item->pkpa_enrollment_requirement_id] = [
                    'effective_date' => $item->proposed_snapshot['effective_date'],
                    'reason' => $request->reason,
                ];
            }
        }

        try {
            return DB::transaction(function () use ($request, $replacement, $actor, $supervisorContexts) {
                $newPublication = $this->publicationService->createRevisionFromPublication($request->publication, $replacement, $actor);
                foreach ($request->items as $item) {
                    $applied = $newPublication->assignments()->where('pkpa_enrollment_requirement_id', $item->pkpa_enrollment_requirement_id)->first();
                    $item->update(['applied_published_assignment_id' => $applied?->id]);
                    if ($item->change_type === 'internal_supervisor_change') {
                        $this->syncSourceInternalSupervisor($item, $actor);
                    }
                }
                $sync = $this->rotationSyncService->sync($newPublication, $actor, $supervisorContexts);
                $request->update([
                    'status' => 'applied',
                    'impact_summary' => array_merge($request->impact_summary ?? [], ['runtime_sync' => $sync]),
                ]);
                $this->audit->record($actor, 'placement_change_request_applied', $request, null, ['publication_id' => $newPublication->id, 'runtime_sync' => $sync]);

                return $newPublication;
            });
        } catch (\Throwable $exception) {
            $request->update([
                'status' => 'failed',
                'impact_summary' => array_merge($request->impact_summary ?? [], ['error' => str($exception->getMessage())->limit(180)->toString()]),
            ]);
            throw $exception;
        }
    }

    private function syncSourceInternalSupervisor(PkpaPlacementChangeRequestItem $item, ?User $actor): void
    {
        $sourceAssignmentId = $item->oldAssignment?->source_rotation_assignment_id;
        $eligibility = PkpaInternalSupervisorEligibility::find($item->proposed_snapshot['internal_supervisor_eligibility_id'] ?? null);
        if (! $sourceAssignmentId || ! $eligibility) {
            return;
        }

        $effectiveDate = Carbon::parse($item->proposed_snapshot['effective_date']);
        PkpaRotationAssignmentSupervisor::query()
            ->where('pkpa_rotation_assignment_id', $sourceAssignmentId)
            ->where('supervisor_type', 'internal')
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'effective_end_date' => $effectiveDate->copy()->subDay()->toDateString(),
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);

        PkpaRotationAssignmentSupervisor::create([
            'pkpa_rotation_assignment_id' => $sourceAssignmentId,
            'supervisor_type' => 'internal',
            'internal_supervisor_eligibility_id' => $eligibility->id,
            'core_user_id' => $eligibility->core_user_id,
            'name_snapshot' => $eligibility->display_name,
            'role_snapshot' => $eligibility->role_snapshot,
            'effective_start_date' => $effectiveDate->toDateString(),
            'effective_end_date' => $item->oldAssignment?->end_date?->toDateString(),
            'status' => 'active',
            'is_primary' => true,
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
    }
}

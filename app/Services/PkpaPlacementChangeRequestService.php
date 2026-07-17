<?php

namespace App\Services;

use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPlacementChangeRequestItem;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPlacementChangeRequestService
{
    public function __construct(
        private readonly PkpaPlacementPublicationService $publicationService,
        private readonly PkpaAuditService $audit,
    ) {
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
        foreach ($request->items()->with('oldAssignment.supervisors')->get() as $item) {
            $snapshot = $item->oldAssignment->toArray();
            foreach (($item->proposed_snapshot ?? []) as $key => $value) {
                if (array_key_exists($key, $snapshot) || in_array($key, ['start_date', 'end_date', 'notes'], true)) {
                    $snapshot[$key] = $value;
                }
            }
            $replacement[$item->old_published_assignment_id] = $snapshot;
        }

        try {
            $newPublication = $this->publicationService->createRevisionFromPublication($request->publication, $replacement, $actor);
            foreach ($request->items as $item) {
                $applied = $newPublication->assignments()->where('pkpa_enrollment_requirement_id', $item->pkpa_enrollment_requirement_id)->first();
                $item->update(['applied_published_assignment_id' => $applied?->id]);
            }
            $request->update(['status' => 'applied']);
            $this->audit->record($actor, 'placement_change_request_applied', $request, null, ['publication_id' => $newPublication->id]);

            return $newPublication;
        } catch (\Throwable $exception) {
            $request->update(['status' => 'failed', 'impact_summary' => ['error' => str($exception->getMessage())->limit(180)->toString()]]);
            throw $exception;
        }
    }
}

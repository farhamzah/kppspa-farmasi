<?php

namespace App\Services;

use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaPublishedAssignmentSupervisor;
use App\Models\PkpaRotationAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPlacementPublicationService
{
    public function __construct(
        private readonly PkpaPlacementReviewService $reviewService,
        private readonly PkpaPlacementNotificationService $notificationService,
        private readonly PkpaAuditService $audit,
    ) {
    }

    public function publish(PkpaPlacementPlan $plan, array $data, ?User $actor): PkpaPlacementPublication
    {
        if (! config('my_pkpa.publication_enabled')) {
            throw ValidationException::withMessages(['publication' => 'Publikasi penempatan sedang dinonaktifkan.']);
        }
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Koordinator PKPA yang dapat mempublikasikan jadwal.']);
        }
        if (($data['confirmation'] ?? null) !== $plan->program?->code) {
            throw ValidationException::withMessages(['confirmation' => 'Konfirmasi harus sama dengan kode program.']);
        }

        $publication = DB::transaction(function () use ($plan, $data, $actor) {
            $plan = PkpaPlacementPlan::with(['program', 'assignments.supervisors'])->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $existing = PkpaPlacementPublication::where('pkpa_placement_plan_id', $plan->id)->where('status', 'published')->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $review = $this->reviewService->review($plan, $actor, true);
            if (! $review['ready']) {
                throw ValidationException::withMessages(['review' => 'Rancangan belum memenuhi checklist publikasi.']);
            }

            $number = ((int) PkpaPlacementPublication::where('pkpa_program_id', $plan->pkpa_program_id)->lockForUpdate()->max('publication_number')) + 1;
            PkpaPlacementPublication::where('pkpa_program_id', $plan->pkpa_program_id)
                ->where('is_current', true)
                ->where('status', 'published')
                ->update(['is_current' => false, 'current_key' => null, 'status' => 'superseded']);

            $publication = PkpaPlacementPublication::create([
                'pkpa_program_id' => $plan->pkpa_program_id,
                'pkpa_placement_plan_id' => $plan->id,
                'publication_number' => $number,
                'revision_number' => 0,
                'code' => $this->code($plan, $number, 0),
                'title' => $data['title'] ?? 'Jadwal Penempatan PKPA '.$plan->program->name,
                'status' => 'publishing',
                'is_current' => false,
                'effective_at' => $data['effective_at'] ?? now(),
                'summary' => ['note' => $data['note'] ?? null] + $review,
                'validation_snapshot' => $review,
                'published_by_core_user_id' => $actor?->core_user_id,
            ]);

            $this->snapshotAssignments($publication, $plan);
            $publication->update([
                'status' => 'published',
                'is_current' => true,
                'current_key' => 'PROGRAM:'.$plan->pkpa_program_id,
                'published_at' => now(),
                'summary' => array_merge($publication->summary ?? [], [
                    'assignments' => $publication->assignments()->count(),
                    'students' => $publication->assignments()->distinct('student_core_user_id')->count('student_core_user_id'),
                ]),
            ]);
            $this->audit->record($actor, 'placement_publication_published', $publication, null, $publication->only(['code', 'publication_number', 'revision_number']));

            return $publication->fresh(['assignments.supervisors']);
        });

        DB::afterCommit(fn () => $this->notificationService->createPublicationNotifications($publication, 'placement_published'));

        return $publication;
    }

    public function syncLockedPlanToPortal(PkpaPlacementPlan $plan, ?User $actor): PkpaPlacementPublication
    {
        return DB::transaction(function () use ($plan, $actor) {
            $plan = PkpaPlacementPlan::with(['program', 'assignments.supervisors'])->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($plan->status !== 'locked') {
                throw ValidationException::withMessages(['plan' => 'Rancangan harus dikunci lebih dulu sebelum ditampilkan ke portal.']);
            }

            $existing = PkpaPlacementPublication::where('pkpa_placement_plan_id', $plan->id)
                ->where('status', 'published')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->fresh(['assignments.supervisors']);
            }

            $review = $this->reviewService->review($plan, $actor, true);
            $number = ((int) PkpaPlacementPublication::where('pkpa_program_id', $plan->pkpa_program_id)->lockForUpdate()->max('publication_number')) + 1;

            PkpaPlacementPublication::where('pkpa_program_id', $plan->pkpa_program_id)
                ->where('is_current', true)
                ->where('status', 'published')
                ->update(['is_current' => false, 'current_key' => null, 'status' => 'superseded']);

            $publication = PkpaPlacementPublication::create([
                'pkpa_program_id' => $plan->pkpa_program_id,
                'pkpa_placement_plan_id' => $plan->id,
                'publication_number' => $number,
                'revision_number' => 0,
                'code' => $this->code($plan, $number, 0),
                'title' => 'Jadwal Portal '.$plan->program->name,
                'status' => 'published',
                'is_current' => true,
                'current_key' => 'PROGRAM:'.$plan->pkpa_program_id,
                'effective_at' => now(),
                'published_at' => now(),
                'summary' => [
                    'note' => 'Terbentuk otomatis saat rancangan dikunci. Hanya assignment valid yang ditampilkan ke portal.',
                ] + $review,
                'validation_snapshot' => $review,
                'published_by_core_user_id' => $actor?->core_user_id,
            ]);

            $this->snapshotAssignments($publication, $plan, true);
            $publication->update([
                'summary' => array_merge($publication->summary ?? [], [
                    'assignments' => $publication->assignments()->count(),
                    'students' => $publication->assignments()->distinct('student_core_user_id')->count('student_core_user_id'),
                ]),
            ]);

            $this->audit->record($actor, 'placement_publication_synced_from_locked_plan', $publication, null, [
                'code' => $publication->code,
                'assignments' => $publication->assignments()->count(),
            ]);

            return $publication->fresh(['assignments.supervisors']);
        });
    }

    public function withdraw(PkpaPlacementPublication $publication, string $reason, ?User $actor): PkpaPlacementPublication
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Koordinator PKPA yang dapat menarik publication.']);
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['withdrawal_reason' => 'Alasan withdrawal wajib diisi.']);
        }

        $publication->update([
            'status' => 'withdrawn',
            'is_current' => false,
            'current_key' => null,
            'withdrawn_at' => now(),
            'withdrawal_reason' => $reason,
            'withdrawn_by_core_user_id' => $actor?->core_user_id,
        ]);
        $this->audit->record($actor, 'placement_publication_withdrawn', $publication, null, ['reason' => $reason]);
        $this->notificationService->createPublicationNotifications($publication, 'placement_withdrawn');

        return $publication->refresh();
    }

    public function createRevisionFromPublication(PkpaPlacementPublication $source, array $replacementSnapshots, ?User $actor, string $eventType = 'placement_revised'): PkpaPlacementPublication
    {
        $publication = DB::transaction(function () use ($source, $replacementSnapshots, $actor) {
            $source = PkpaPlacementPublication::with('assignments.supervisors', 'program', 'plan')->whereKey($source->id)->lockForUpdate()->firstOrFail();
            if ($source->status !== 'published') {
                throw ValidationException::withMessages(['publication' => 'Hanya publication published yang dapat direvisi.']);
            }
            $number = ((int) PkpaPlacementPublication::where('pkpa_program_id', $source->pkpa_program_id)->lockForUpdate()->max('publication_number')) + 1;
            $revision = ((int) PkpaPlacementPublication::where('pkpa_program_id', $source->pkpa_program_id)->max('revision_number')) + 1;
            $source->update(['status' => 'superseded', 'is_current' => false, 'current_key' => null]);
            $new = PkpaPlacementPublication::create([
                'pkpa_program_id' => $source->pkpa_program_id,
                'pkpa_placement_plan_id' => $source->pkpa_placement_plan_id,
                'publication_number' => $number,
                'revision_number' => $revision,
                'code' => $this->code($source->plan, $number, $revision),
                'title' => $source->title.' Revisi '.$revision,
                'status' => 'published',
                'is_current' => true,
                'current_key' => 'PROGRAM:'.$source->pkpa_program_id,
                'published_at' => now(),
                'effective_at' => now(),
                'summary' => $source->summary,
                'validation_snapshot' => $source->validation_snapshot,
                'published_by_core_user_id' => $actor?->core_user_id,
            ]);

            foreach ($source->assignments as $assignment) {
                $snapshot = $replacementSnapshots[$assignment->id] ?? $assignment->toArray();
                $copy = PkpaPublishedAssignment::create(array_merge(
                    collect($snapshot)->only((new PkpaPublishedAssignment)->getFillable())->except(['id', 'created_at', 'updated_at'])->all(),
                    [
                    'pkpa_placement_publication_id' => $new->id,
                    'status' => isset($replacementSnapshots[$assignment->id]) ? 'revised' : $assignment->status,
                    ]
                ));
                foreach ($assignment->supervisors as $supervisor) {
                    PkpaPublishedAssignmentSupervisor::create($supervisor->replicate(['id', 'created_at', 'updated_at'])->fill([
                        'pkpa_published_assignment_id' => $copy->id,
                    ])->toArray());
                }
            }

            $this->audit->record($actor, 'placement_revision_applied', $new, ['source_publication_id' => $source->id], ['code' => $new->code]);

            return $new->fresh('assignments.supervisors');
        });

        DB::afterCommit(fn () => $this->notificationService->createPublicationNotifications($publication, $eventType));

        return $publication;
    }

    private function snapshotAssignments(PkpaPlacementPublication $publication, PkpaPlacementPlan $plan, bool $validOnly = false): void
    {
        $assignments = $plan->assignments()
            ->whereHas('programDomain', fn ($query) => $query->where('is_active', true))
            ->with(['enrollment.activeGroupMembership.group', 'requirement', 'practiceDomain', 'selectedOption', 'practiceSite', 'programSite', 'availabilityPeriod', 'supervisors.internalEligibility', 'supervisors.fieldSupervisor'])
            ->whereNotIn('status', ['cancelled', 'superseded'])
            ->when($validOnly, fn ($query) => $query->where('status', 'valid'))
            ->get();

        foreach ($assignments as $assignment) {
            /** @var PkpaRotationAssignment $assignment */
            $published = PkpaPublishedAssignment::create([
                'pkpa_placement_publication_id' => $publication->id,
                'source_rotation_assignment_id' => $assignment->id,
                'pkpa_enrollment_id' => $assignment->pkpa_enrollment_id,
                'pkpa_enrollment_requirement_id' => $assignment->pkpa_enrollment_requirement_id,
                'practice_domain_id' => $assignment->practice_domain_id,
                'practice_domain_option_id' => $assignment->selected_practice_domain_option_id,
                'practice_site_id' => $assignment->practice_site_id,
                'program_site_id' => $assignment->pkpa_program_site_id,
                'availability_period_id' => $assignment->pkpa_site_availability_period_id,
                'student_core_user_id' => $assignment->enrollment->core_user_id,
                'student_number_snapshot' => $assignment->enrollment->student_number,
                'student_name_snapshot' => $assignment->enrollment->student_name_snapshot,
                'student_group_snapshot' => $assignment->enrollment->activeGroupMembership?->group?->name,
                'practice_domain_name_snapshot' => $assignment->practiceDomain?->name,
                'practice_domain_option_name_snapshot' => $assignment->selectedOption?->name,
                'practice_site_name_snapshot' => $assignment->practiceSite?->name,
                'practice_site_address_snapshot' => $assignment->practiceSite?->address,
                'start_date' => $assignment->start_date,
                'end_date' => $assignment->end_date,
                'duration_value_snapshot' => $assignment->planned_duration_value,
                'duration_unit_snapshot' => $assignment->planned_duration_unit,
                'effective_days_snapshot' => $assignment->calculated_effective_days,
                'practice_hours_snapshot' => $assignment->calculated_practice_hours,
                'status' => 'scheduled',
                'notes' => $assignment->notes,
            ]);

            foreach ($assignment->supervisors as $supervisor) {
                $user = User::query()
                    ->with(['lecturer', 'fieldSupervisor'])
                    ->where('core_user_id', $supervisor->core_user_id)
                    ->first();

                PkpaPublishedAssignmentSupervisor::create([
                    'pkpa_published_assignment_id' => $published->id,
                    'source_assignment_supervisor_id' => $supervisor->id,
                    'supervisor_type' => $supervisor->supervisor_type,
                    'core_user_id' => $supervisor->core_user_id,
                    'name_snapshot' => $user
                        ? user_display_name($user, $supervisor->supervisor_type === 'internal' ? 'pembimbing_dalam' : 'pembimbing_lapangan')
                        : $supervisor->name_snapshot,
                    'email_snapshot' => $supervisor->internalEligibility?->email_snapshot ?: $supervisor->fieldSupervisor?->email_snapshot,
                    'role_snapshot' => $supervisor->role_snapshot,
                    'position_snapshot' => $supervisor->fieldSupervisor?->position_title,
                    'is_primary' => true,
                    'status' => 'assigned',
                ]);
            }
        }
    }

    private function code(PkpaPlacementPlan $plan, int $number, int $revision): string
    {
        return $plan->program?->code.'-PUB-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'-R'.$revision;
    }
}

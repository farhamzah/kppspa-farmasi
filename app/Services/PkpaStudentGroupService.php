<?php

namespace App\Services;

use App\Models\PkpaEnrollment;
use App\Models\PkpaStudentGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaStudentGroupService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function create(array $data, ?User $actor): PkpaStudentGroup
    {
        $group = PkpaStudentGroup::create($this->normalize($data) + [
            'created_by_core_user_id' => $actor?->core_user_id,
            'updated_by_core_user_id' => $actor?->core_user_id,
        ]);
        $this->audit->record($actor, 'student_group_created', $group, null, $group->only(['pkpa_program_id', 'code', 'name']));

        return $group;
    }

    public function update(PkpaStudentGroup $group, array $data, ?User $actor): PkpaStudentGroup
    {
        $old = $group->only(array_keys($data));
        $group->update($this->normalize($data) + ['updated_by_core_user_id' => $actor?->core_user_id]);
        $this->audit->record($actor, 'student_group_updated', $group, $old, $group->only(array_keys($data)));

        return $group->refresh();
    }

    public function addMember(PkpaStudentGroup $group, PkpaEnrollment $enrollment, ?User $actor, ?string $notes = null): void
    {
        $this->assertCanJoin($group, $enrollment);

        DB::transaction(function () use ($group, $enrollment, $actor, $notes) {
            $enrollment->groupMemberships()->where('status', 'active')->whereNull('left_at')->get()->each(function ($membership) use ($actor) {
                $membership->update(['status' => 'moved', 'left_at' => now(), 'updated_by_core_user_id' => $actor?->core_user_id]);
            });

            $membership = $group->members()->create([
                'pkpa_enrollment_id' => $enrollment->id,
                'joined_at' => now(),
                'status' => 'active',
                'notes' => $notes,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'student_group_member_added', $membership, null, $membership->only(['pkpa_student_group_id', 'pkpa_enrollment_id']));
        });
    }

    public function addMembers(PkpaStudentGroup $group, array $enrollmentIds, ?User $actor): int
    {
        $count = 0;
        foreach (PkpaEnrollment::whereIn('id', $enrollmentIds)->get() as $enrollment) {
            $this->addMember($group, $enrollment, $actor);
            $count++;
        }

        return $count;
    }

    public function removeMember(PkpaEnrollment $enrollment, ?User $actor): void
    {
        DB::transaction(function () use ($enrollment, $actor) {
            $enrollment->groupMemberships()->where('status', 'active')->whereNull('left_at')->get()->each(function ($membership) use ($actor) {
                $old = $membership->only(['status', 'left_at']);
                $membership->update(['status' => 'left', 'left_at' => now(), 'updated_by_core_user_id' => $actor?->core_user_id]);
                $this->audit->record($actor, 'student_group_member_removed', $membership, $old, $membership->only(['status', 'left_at']));
            });
        });
    }

    private function assertCanJoin(PkpaStudentGroup $group, PkpaEnrollment $enrollment): void
    {
        if ((int) $group->pkpa_program_id !== (int) $enrollment->pkpa_program_id) {
            throw ValidationException::withMessages(['group' => 'Kelompok dan peserta harus berasal dari program yang sama.']);
        }

        if ($group->maximum_members && $group->activeMembers()->count() >= $group->maximum_members) {
            throw ValidationException::withMessages(['group' => 'Kelompok sudah penuh.']);
        }
    }

    private function normalize(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}

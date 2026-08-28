<?php

namespace App\Services;

use App\Models\FieldSupervisor;
use App\Models\KpAssignment;
use App\Models\KpPlaceFieldSupervisor;
use App\Models\KpPlaceSelection;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpAssignmentService
{
    public function createFromSelection(User $actor, KpPlaceSelection $selection, ?int $internalSupervisorId = null, ?int $fieldSupervisorId = null, ?string $note = null): KpAssignment
    {
        return DB::transaction(function () use ($actor, $selection, $internalSupervisorId, $fieldSupervisorId, $note) {
            $selection = KpPlaceSelection::query()->with(['registration', 'student', 'place'])->lockForUpdate()->findOrFail($selection->id);

            if ($selection->status !== 'aktif') {
                throw ValidationException::withMessages(['selection' => 'Assignment hanya bisa dibuat dari pilihan tempat yang aktif.']);
            }

            if (KpAssignment::where('kp_period_id', $selection->kp_period_id)->where('student_id', $selection->student_id)->where('status', '!=', 'dibatalkan')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['selection' => 'Mahasiswa sudah memiliki penempatan aktif pada periode ini.']);
            }

            $lecturer = $internalSupervisorId ? Lecturer::findOrFail($internalSupervisorId) : null;
            $fieldSupervisor = $fieldSupervisorId ? FieldSupervisor::findOrFail($fieldSupervisorId) : null;
            if ($lecturer) {
                $this->ensureInternalSupervisor($lecturer);
            }
            if ($fieldSupervisor) {
                $this->ensureFieldSupervisor($fieldSupervisor);
            }

            $status = ($lecturer && $fieldSupervisor) ? 'aktif' : 'menunggu_pembimbing';
            $assignment = KpAssignment::create([
                'kp_period_id' => $selection->kp_period_id,
                'kp_registration_id' => $selection->kp_registration_id,
                'kp_place_selection_id' => $selection->id,
                'student_id' => $selection->student_id,
                'kp_place_id' => $selection->kp_place_id,
                'internal_supervisor_id' => $lecturer?->id,
                'field_supervisor_id' => $fieldSupervisor?->id,
                'status' => $status,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'active_key' => $this->activeKey($selection->kp_period_id, $selection->student_id),
                'note' => $note,
            ]);

            if ($fieldSupervisor) {
                $this->syncPlaceFieldSupervisor($assignment, $fieldSupervisor, $actor);
            }

            $this->logAssignment($actor, $assignment, 'assignment_created', null, $status, null, $lecturer?->id, null, $fieldSupervisor?->id, $note);

            return $assignment;
        });
    }

    public function assignInternalSupervisor(User $actor, KpAssignment $assignment, Lecturer $lecturer, ?string $note = null): KpAssignment
    {
        $this->ensureInternalSupervisor($lecturer);

        return $this->updateSupervisors($actor, $assignment, $lecturer, $assignment->fieldSupervisor, $note, 'internal_supervisor_assigned');
    }

    public function assignFieldSupervisor(User $actor, KpAssignment $assignment, FieldSupervisor $fieldSupervisor, ?string $note = null): KpAssignment
    {
        $this->ensureFieldSupervisor($fieldSupervisor);

        return $this->updateSupervisors($actor, $assignment, $assignment->internalSupervisor, $fieldSupervisor, $note, 'field_supervisor_assigned');
    }

    public function updateSupervisors(User $actor, KpAssignment $assignment, ?Lecturer $lecturer, ?FieldSupervisor $fieldSupervisor, ?string $note = null, string $action = 'supervisors_updated'): KpAssignment
    {
        return DB::transaction(function () use ($actor, $assignment, $lecturer, $fieldSupervisor, $note, $action) {
            $assignment = KpAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($assignment->status === 'dibatalkan') {
                throw ValidationException::withMessages(['assignment' => 'Penempatan yang dibatalkan tidak bisa diperbarui.']);
            }
            if ($lecturer) {
                $this->ensureInternalSupervisor($lecturer);
            }
            if ($fieldSupervisor) {
                $this->ensureFieldSupervisor($fieldSupervisor);
            }

            $oldStatus = $assignment->status;
            $oldInternal = $assignment->internal_supervisor_id;
            $oldField = $assignment->field_supervisor_id;
            $newStatus = ($lecturer && $fieldSupervisor) ? 'aktif' : 'menunggu_pembimbing';

            $assignment->update([
                'internal_supervisor_id' => $lecturer?->id,
                'field_supervisor_id' => $fieldSupervisor?->id,
                'status' => $newStatus,
                'note' => $note ?? $assignment->note,
            ]);

            if ($fieldSupervisor) {
                $this->syncPlaceFieldSupervisor($assignment, $fieldSupervisor, $actor);
            }

            $this->logAssignment($actor, $assignment, $oldStatus === 'menunggu_pembimbing' && $newStatus === 'aktif' ? 'assignment_activated' : $action, $oldStatus, $newStatus, $oldInternal, $lecturer?->id, $oldField, $fieldSupervisor?->id, $note);

            return $assignment->fresh();
        });
    }

    public function cancelAssignment(User $actor, KpAssignment $assignment, string $reason): void
    {
        DB::transaction(function () use ($actor, $assignment, $reason) {
            $assignment = KpAssignment::query()->with('selection.registration')->lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->status === 'dibatalkan') {
                throw ValidationException::withMessages(['assignment' => 'Penempatan PKPA ini sudah dibatalkan.']);
            }

            $oldStatus = $assignment->status;
            $oldInternal = $assignment->internal_supervisor_id;
            $oldField = $assignment->field_supervisor_id;

            $assignment->update([
                'status' => 'dibatalkan',
                'internal_supervisor_id' => null,
                'field_supervisor_id' => null,
                'active_key' => null,
                'note' => $reason,
            ]);

            if ($assignment->selection && $assignment->selection->status === 'aktif') {
                $assignment->selection->update([
                    'status' => 'dibatalkan',
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Penempatan dibatalkan: '.$reason,
                    'active_key' => null,
                ]);
            }

            $this->logAssignment($actor, $assignment, 'assignment_cancelled', $oldStatus, 'dibatalkan', $oldInternal, null, $oldField, null, $reason);
        });
    }

    public function logAssignment(User $actor, KpAssignment $assignment, string $action, ?string $oldStatus, ?string $newStatus, ?int $oldInternal, ?int $newInternal, ?int $oldField, ?int $newField, ?string $note): void
    {
        $assignment->logs()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_internal_supervisor_id' => $oldInternal,
            'new_internal_supervisor_id' => $newInternal,
            'old_field_supervisor_id' => $oldField,
            'new_field_supervisor_id' => $newField,
            'note' => $note,
        ]);
    }

    private function ensureInternalSupervisor(Lecturer $lecturer): void
    {
        if (! $lecturer->user?->hasRole('pembimbing_dalam')) {
            throw ValidationException::withMessages(['internal_supervisor_id' => 'Pembimbing dalam harus memiliki role Pembimbing Dalam.']);
        }
    }

    private function ensureFieldSupervisor(FieldSupervisor $fieldSupervisor): void
    {
        if (! $fieldSupervisor->user?->hasRole('pembimbing_lapangan')) {
            throw ValidationException::withMessages(['field_supervisor_id' => 'Preseptor harus memiliki role Preseptor.']);
        }

        if ($fieldSupervisor->status !== 'active') {
            throw ValidationException::withMessages(['field_supervisor_id' => 'Preseptor harus aktif.']);
        }
    }

    private function syncPlaceFieldSupervisor(KpAssignment $assignment, FieldSupervisor $fieldSupervisor, User $actor): void
    {
        KpPlaceFieldSupervisor::updateOrCreate(
            ['kp_place_id' => $assignment->kp_place_id, 'field_supervisor_id' => $fieldSupervisor->id],
            ['status' => 'aktif', 'created_by' => $actor->id]
        );
    }

    private function activeKey(int $periodId, int $studentId): string
    {
        return $periodId.'-'.$studentId;
    }
}

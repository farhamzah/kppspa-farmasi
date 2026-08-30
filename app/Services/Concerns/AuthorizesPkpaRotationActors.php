<?php

namespace App\Services\Concerns;

use App\Models\PkpaLogbookAttachment;
use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationRun;
use App\Models\User;
use Illuminate\Validation\ValidationException;

trait AuthorizesPkpaRotationActors
{
    private function isCoordinator(?User $actor): bool
    {
        return (bool) $actor?->hasAnyRole(['admin', 'koordinator_kp']);
    }

    private function ensureStudentOwnsRun(PkpaRotationRun $run, ?User $actor): void
    {
        if (! $actor || ! $run->loadMissing('enrollment', 'currentAssignment')->belongsToStudentCoreUser($actor->core_user_id)) {
            throw ValidationException::withMessages(['authorization' => 'Akses rotasi mahasiswa tidak valid.']);
        }
    }

    private function ensureFieldSupervisor(PkpaRotationRun $run, ?User $actor): void
    {
        if ($this->isCoordinator($actor)) {
            return;
        }

        if (! $actor || ! $run->supervisorHistories()
            ->where('supervisor_type', 'field')
            ->where('core_user_id', $actor->core_user_id)
            ->where('status', 'active')
            ->exists()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya preseptor aktif yang dapat memvalidasi data ini.']);
        }
    }

    private function ensureInternalSupervisor(PkpaRotationRun $run, ?User $actor): void
    {
        if ($this->isCoordinator($actor)) {
            return;
        }

        if (! $actor || ! $run->supervisorHistories()
            ->where('supervisor_type', 'internal')
            ->where('core_user_id', $actor->core_user_id)
            ->where('status', 'active')
            ->exists()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya pembimbing dalam aktif yang dapat memantau logbook ini.']);
        }
    }

    private function ensureCanAccessLogbook(PkpaLogbookEntry $entry, ?User $actor): void
    {
        $run = $entry->rotationRun()->with('supervisorHistories')->firstOrFail();
        if ($this->isCoordinator($actor) || ($actor && $run->loadMissing('enrollment', 'currentAssignment')->belongsToStudentCoreUser($actor->core_user_id))) {
            return;
        }

        if ($actor && $run->supervisorHistories->contains(fn ($supervisor) => $supervisor->core_user_id === $actor->core_user_id && $supervisor->status === 'active')) {
            return;
        }

        throw ValidationException::withMessages(['authorization' => 'Lampiran logbook bersifat privat untuk pihak yang berwenang.']);
    }

    private function ensureCanAccessAttachment(PkpaLogbookAttachment $attachment, ?User $actor): void
    {
        $this->ensureCanAccessLogbook($attachment->logbookEntry()->firstOrFail(), $actor);
    }
}

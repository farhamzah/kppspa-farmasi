<?php

namespace App\Services;

use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeDomainOption;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPracticeDomainService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function createDomain(array $data, ?User $actor): PkpaPracticeDomain
    {
        return DB::transaction(function () use ($data, $actor) {
            $domain = PkpaPracticeDomain::create($this->normalize($data) + [
                'is_system' => false,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'practice_domain_created', $domain, null, $domain->only(['code', 'name']));

            return $domain;
        });
    }

    public function updateDomain(PkpaPracticeDomain $domain, array $data, ?User $actor): PkpaPracticeDomain
    {
        if ($domain->is_system && array_key_exists('is_active', $data) && ! $data['is_active'] && empty($data['confirm_system_deactivation'])) {
            throw ValidationException::withMessages(['is_active' => 'Nonaktifkan wahana sistem hanya setelah dampaknya dikonfirmasi.']);
        }

        unset($data['confirm_system_deactivation']);

        return DB::transaction(function () use ($domain, $data, $actor) {
            $old = $domain->only(array_keys($data));
            $domain->update($this->normalize($data) + ['updated_by_core_user_id' => $actor?->core_user_id]);
            $this->audit->record($actor, 'practice_domain_updated', $domain, $old, $domain->only(array_keys($data)));

            return $domain->refresh();
        });
    }

    public function deleteDomain(PkpaPracticeDomain $domain, ?User $actor): void
    {
        if (! $domain->canBeDeleted()) {
            throw ValidationException::withMessages(['domain' => 'Wahana sistem tidak dapat dihapus.']);
        }

        DB::transaction(function () use ($domain, $actor) {
            if ($domain->isLegacyStandalonePuskesmas()) {
                $this->normalizeLegacyPuskesmas($domain, $actor);
            }

            $old = $domain->only(['code', 'name']);
            $domain->delete();
            $this->audit->record($actor, 'practice_domain_deleted', $domain, $old, null);
        });
    }

    private function normalizeLegacyPuskesmas(PkpaPracticeDomain $legacyDomain, ?User $actor): void
    {
        $government = PkpaPracticeDomain::query()->where('code', 'PEM')->first();

        if (! $government) {
            throw ValidationException::withMessages(['domain' => 'Wahana Pemerintahan belum tersedia, jadi Puskesmas legacy belum bisa dipindahkan.']);
        }

        $now = Carbon::now();
        $puskesmasOption = PkpaPracticeDomainOption::withTrashed()
            ->where('practice_domain_id', $government->id)
            ->where('code', 'PUSKESMAS')
            ->first() ?? new PkpaPracticeDomainOption([
                'practice_domain_id' => $government->id,
                'code' => 'PUSKESMAS',
            ]);

        $puskesmasOption->practice_domain_id = $government->id;
        $puskesmasOption->code = 'PUSKESMAS';
        $puskesmasOption->name = 'Puskesmas';
        $puskesmasOption->description = 'Sub-wahana Pemerintahan untuk jejaring Puskesmas.';
        $puskesmasOption->is_system = true;
        $puskesmasOption->is_active = true;
        $puskesmasOption->sort_order = 20;
        $puskesmasOption->created_by_core_user_id ??= $actor?->core_user_id;
        $puskesmasOption->updated_by_core_user_id = $actor?->core_user_id;
        $puskesmasOption->deleted_at = null;
        $puskesmasOption->save();

        DB::table('pkpa_practice_sites')
            ->where('practice_domain_id', $legacyDomain->id)
            ->update([
                'practice_domain_id' => $government->id,
                'practice_domain_option_id' => $puskesmasOption->id,
                'updated_at' => $now,
            ]);

        DB::table('pkpa_program_sites')
            ->where('practice_domain_id', $legacyDomain->id)
            ->update([
                'practice_domain_id' => $government->id,
                'practice_domain_option_id' => $puskesmasOption->id,
                'updated_at' => $now,
            ]);

        DB::table('pkpa_internal_supervisor_eligibilities')
            ->where('practice_domain_id', $legacyDomain->id)
            ->update([
                'practice_domain_id' => $government->id,
                'updated_at' => $now,
            ]);

        DB::table('pkpa_program_domains')
            ->where('practice_domain_id', $legacyDomain->id)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);
    }

    public function createOption(PkpaPracticeDomain $domain, array $data, ?User $actor): PkpaPracticeDomainOption
    {
        return DB::transaction(function () use ($domain, $data, $actor) {
            $option = $domain->options()->create($this->normalize($data) + [
                'is_system' => false,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'practice_domain_option_created', $option, null, $option->only(['code', 'name']));

            return $option;
        });
    }

    public function updateOption(PkpaPracticeDomainOption $option, array $data, ?User $actor): PkpaPracticeDomainOption
    {
        return DB::transaction(function () use ($option, $data, $actor) {
            $old = $option->only(array_keys($data));
            $option->update($this->normalize($data) + ['updated_by_core_user_id' => $actor?->core_user_id]);
            $this->audit->record($actor, 'practice_domain_option_updated', $option, $old, $option->only(array_keys($data)));

            return $option->refresh();
        });
    }

    public function deleteOption(PkpaPracticeDomainOption $option, ?User $actor): void
    {
        if ($option->is_system) {
            throw ValidationException::withMessages(['option' => 'Pilihan sistem tidak dapat dihapus.']);
        }

        DB::transaction(function () use ($option, $actor) {
            $old = $option->only(['code', 'name']);
            $option->delete();
            $this->audit->record($actor, 'practice_domain_option_deleted', $option, $old, null);
        });
    }

    private function normalize(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}

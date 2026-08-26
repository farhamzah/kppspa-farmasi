<?php

namespace App\Services;

use App\Models\PkpaPracticeDomain;
use App\Models\PkpaPracticeSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaPracticeSiteService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function query(array $filters): Builder
    {
        return PkpaPracticeSite::query()
            ->with(['practiceDomain', 'practiceDomainOption'])
            ->withTrashed()
            ->search($filters['q'] ?? null)
            ->when($filters['practice_domain_id'] ?? null, fn (Builder $query, $id) => $query->where('practice_domain_id', $id))
            ->when($filters['practice_domain_option_id'] ?? null, fn (Builder $query, $id) => $query->where('practice_domain_option_id', $id))
            ->when($filters['city'] ?? null, fn (Builder $query, $city) => $query->where('city', 'like', '%'.$city.'%'))
            ->when($filters['province'] ?? null, fn (Builder $query, $province) => $query->where('province', 'like', '%'.$province.'%'))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when(($filters['active'] ?? '') !== '', fn (Builder $query) => $query->where('is_active', (bool) ($filters['active'] ?? false)))
            ->when(($filters['cooperation'] ?? '') === 'expired', fn (Builder $query) => $query->whereDate('cooperation_end_date', '<', now()))
            ->when(($filters['cooperation'] ?? '') === 'valid', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->whereNull('cooperation_end_date')->orWhereDate('cooperation_end_date', '>=', now())));
    }

    public function create(array $data, ?User $actor): PkpaPracticeSite
    {
        $this->validateDomainOption($data);

        return DB::transaction(function () use ($data, $actor) {
            $site = PkpaPracticeSite::create($this->normalize($data) + [
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'practice_site_created', $site, null, $site->only(['code', 'name', 'practice_domain_id']));

            return $site;
        });
    }

    public function update(PkpaPracticeSite $site, array $data, ?User $actor): PkpaPracticeSite
    {
        $this->validateDomainOption($data + [
            'practice_domain_id' => $data['practice_domain_id'] ?? $site->practice_domain_id,
            'practice_domain_option_id' => $data['practice_domain_option_id'] ?? $site->practice_domain_option_id,
        ]);

        return DB::transaction(function () use ($site, $data, $actor) {
            $old = $site->only(array_keys($data));
            $site->update($this->normalize($data) + ['updated_by_core_user_id' => $actor?->core_user_id]);
            $this->audit->record($actor, 'practice_site_updated', $site, $old, $site->only(array_keys($data)));

            return $site->refresh();
        });
    }

    public function delete(PkpaPracticeSite $site, ?User $actor): void
    {
        DB::transaction(function () use ($site, $actor) {
            $old = $site->only(['code', 'name']);
            $site->delete();
            $this->audit->record($actor, 'practice_site_deleted', $site, $old, null);
        });
    }

    private function validateDomainOption(array $data): void
    {
        $domain = PkpaPracticeDomain::find($data['practice_domain_id'] ?? null);
        if (! $domain) {
            return;
        }

        $optionId = $data['practice_domain_option_id'] ?? null;
        if ($domain->isGovernment() && blank($optionId)) {
            throw ValidationException::withMessages(['practice_domain_option_id' => 'Tempat Pemerintahan wajib memilih Dinas Kesehatan, Puskesmas, atau Loka BPOM.']);
        }

        if ($optionId && ! $domain->options()->whereKey($optionId)->exists()) {
            throw ValidationException::withMessages(['practice_domain_option_id' => 'Pilihan/subjenis harus berasal dari wahana yang sama.']);
        }
    }

    private function normalize(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        if (($data['practice_domain_option_id'] ?? '') === '') {
            $data['practice_domain_option_id'] = null;
        }

        return $data;
    }
}

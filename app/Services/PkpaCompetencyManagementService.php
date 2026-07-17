<?php

namespace App\Services;

use App\Models\PkpaCompetencyCategory;
use App\Models\PkpaCompetencyItem;
use App\Models\PkpaCompetencySet;
use App\Models\PkpaProgramDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaCompetencyManagementService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function createSet(PkpaProgramDomain $programDomain, array $data, ?User $actor): PkpaCompetencySet
    {
        $this->ensureManager($actor);

        return DB::transaction(function () use ($programDomain, $data, $actor) {
            $version = (int) ($data['version_number'] ?? (PkpaCompetencySet::where('pkpa_program_domain_id', $programDomain->id)->max('version_number') + 1));
            $set = PkpaCompetencySet::create([
                'pkpa_program_domain_id' => $programDomain->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version_number' => max(1, $version),
                'status' => $data['status'] ?? 'draft',
                'instructions' => $data['instructions'] ?? null,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'pkpa_competency_set_created', $set);

            return $set;
        });
    }

    public function activate(PkpaCompetencySet $set, ?User $actor): PkpaCompetencySet
    {
        if (! $actor?->hasRole('koordinator_kp')) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Koordinator PKPA yang dapat mengaktifkan set kompetensi.']);
        }
        if ($set->items()->where('is_active', true)->count() === 0) {
            throw ValidationException::withMessages(['items' => 'Set kompetensi harus memiliki item aktif sebelum diaktifkan.']);
        }

        return DB::transaction(function () use ($set, $actor) {
            PkpaCompetencySet::where('pkpa_program_domain_id', $set->pkpa_program_domain_id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'current_key' => null, 'status' => 'superseded']);
            $set->update([
                'status' => 'active',
                'is_current' => true,
                'current_key' => 'PROGRAM_DOMAIN:'.$set->pkpa_program_domain_id,
                'activated_by_core_user_id' => $actor?->core_user_id,
                'activated_at' => now(),
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]);
            $this->audit->record($actor, 'pkpa_competency_set_activated', $set);

            return $set->refresh();
        });
    }

    public function saveCategory(PkpaCompetencySet $set, array $data, ?User $actor): PkpaCompetencyCategory
    {
        $this->ensureManager($actor);

        return tap(PkpaCompetencyCategory::updateOrCreate(
            ['pkpa_competency_set_id' => $set->id, 'code' => $data['code']],
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'updated_by_core_user_id' => $actor?->core_user_id,
                'created_by_core_user_id' => $actor?->core_user_id,
            ]
        ), fn ($category) => $this->audit->record($actor, 'pkpa_competency_category_saved', $category));
    }

    public function saveItem(PkpaCompetencySet $set, array $data, ?User $actor): PkpaCompetencyItem
    {
        $this->ensureManager($actor);
        if (($data['minimum_evidence_count'] ?? 0) < 0) {
            throw ValidationException::withMessages(['minimum_evidence_count' => 'Jumlah bukti minimum tidak boleh negatif.']);
        }
        if (($data['evidence_required'] ?? false) && (int) ($data['minimum_evidence_count'] ?? 0) < 1) {
            throw ValidationException::withMessages(['minimum_evidence_count' => 'Kompetensi yang mewajibkan bukti harus memiliki minimum bukti minimal 1.']);
        }

        return tap(PkpaCompetencyItem::updateOrCreate(
            ['pkpa_competency_set_id' => $set->id, 'code' => $data['code']],
            [
                'pkpa_competency_category_id' => $data['pkpa_competency_category_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'achievement_criteria' => $data['achievement_criteria'] ?? null,
                'evidence_instructions' => $data['evidence_instructions'] ?? null,
                'is_required' => $data['is_required'] ?? true,
                'evidence_required' => $data['evidence_required'] ?? false,
                'minimum_evidence_count' => $data['minimum_evidence_count'] ?? 0,
                'verification_required' => $data['verification_required'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'updated_by_core_user_id' => $actor?->core_user_id,
                'created_by_core_user_id' => $actor?->core_user_id,
            ]
        ), fn ($item) => $this->audit->record($actor, 'pkpa_competency_item_saved', $item));
    }

    private function ensureManager(?User $actor): void
    {
        if (! $actor?->hasAnyRole(['admin', 'koordinator_kp'])) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin atau Koordinator PKPA yang dapat mengelola master akademik.']);
        }
    }
}

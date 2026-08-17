<?php

namespace App\Services\Integration;

use App\Models\KpPeriod;

class KpSafaPublicInfoPreviewService
{
    public function preview(?int $periodId = null): array
    {
        $period = $this->period($periodId);

        return [
            'source_app' => (string) config('core_farmasi.app_code', 'kppspa-farmasi'),
            'contract_version' => 'kp-safa-public-v1',
            'dry_run' => true,
            'external_request_sent' => false,
            'generated_at' => now()->toIso8601String(),
            'public_visibility' => 'public_safe_preview',
            'period' => $period ? $this->periodSnapshot($period) : null,
            'timeline' => $period ? $this->timeline($period) : [],
            'requirements' => $period ? $this->requirements($period) : [],
            'announcements' => $period ? $this->announcements($period) : [],
            'contact' => [
                'unit' => 'Program Studi Farmasi UBP',
                'source' => 'manual_admin_info_placeholder',
                'email' => null,
                'phone' => null,
            ],
            'registration_status' => $period ? [
                'status' => $period->status,
                'label' => $period->statusLabel(),
                'registration_open' => $period->isRegistrationOpen(),
                'selection_open' => $period->isSelectionOpen(),
            ] : null,
            'private_data_excluded' => true,
            'validation_warnings' => $period ? [] : ['No KP period found for public preview.'],
        ];
    }

    private function period(?int $periodId): ?KpPeriod
    {
        return KpPeriod::query()
            ->with('documentRequirements')
            ->when($periodId, fn ($query) => $query->whereKey($periodId))
            ->orderByRaw("CASE WHEN status = 'dibuka' THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END")
            ->latest('id')
            ->first();
    }

    private function periodSnapshot(KpPeriod $period): array
    {
        return [
            'kp_period_id' => $period->id,
            'name' => $period->name,
            'academic_year' => $period->academic_year,
            'semester' => $period->semester,
            'status' => $period->status,
            'status_label' => $period->statusLabel(),
            'description' => $period->description,
        ];
    }

    private function timeline(KpPeriod $period): array
    {
        return [
            ['key' => 'registration', 'label' => 'Pendaftaran KP', 'start' => $period->registration_start_at?->toIso8601String(), 'end' => $period->registration_end_at?->toIso8601String()],
            ['key' => 'document_verification', 'label' => 'Verifikasi Berkas', 'start' => $period->document_verification_start_at?->toIso8601String(), 'end' => $period->document_verification_end_at?->toIso8601String()],
            ['key' => 'place_selection', 'label' => 'Pemilihan Tempat KP', 'start' => $period->selection_start_at?->toIso8601String(), 'end' => $period->selection_end_at?->toIso8601String()],
            ['key' => 'kp_execution', 'label' => 'Pelaksanaan KP', 'start' => $period->kp_start_date?->toDateString(), 'end' => $period->kp_end_date?->toDateString()],
        ];
    }

    private function requirements(KpPeriod $period): array
    {
        return $period->documentRequirements
            ->where('status', 'aktif')
            ->values()
            ->map(fn ($requirement) => [
                'name' => $requirement->name,
                'description' => $requirement->description,
                'is_required' => (bool) $requirement->is_required,
                'allowed_file_types' => $requirement->allowedFileTypesArray(),
                'max_file_size_mb' => $requirement->max_file_size_mb,
                'sort_order' => $requirement->sort_order,
            ])
            ->all();
    }

    private function announcements(KpPeriod $period): array
    {
        return [
            [
                'title' => 'Informasi Kerja Praktek Farmasi',
                'body' => $period->description ?: 'Informasi periode Kerja Praktek Farmasi tersedia di aplikasi KP Farmasi UBP.',
                'type' => 'kp_period',
                'period_name' => $period->name,
                'safe_link_target' => 'kppspa-farmasi-login',
                'token_url' => false,
            ],
        ];
    }
}

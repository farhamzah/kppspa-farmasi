<?php

namespace App\Services;

use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PkpaProgramService
{
    public function __construct(private readonly PkpaAuditService $audit)
    {
    }

    public function create(array $data, ?User $actor): PkpaProgram
    {
        return DB::transaction(function () use ($data, $actor) {
            unset($data['status'], $data['is_active'], $data['activated_at'], $data['activated_by_core_user_id']);

            $program = PkpaProgram::create(array_merge($data, [
                'status' => 'draft',
                'is_active' => false,
                'created_by_core_user_id' => $actor?->core_user_id,
                'updated_by_core_user_id' => $actor?->core_user_id,
            ]));

            $this->ensureDefaultDomains($program, $actor);
            $this->audit->record($actor, 'program_created', $program, null, $program->only(['code', 'name', 'status']));

            return $program->load('domains.practiceDomain.options');
        });
    }

    public function update(PkpaProgram $program, array $data, ?User $actor): PkpaProgram
    {
        return DB::transaction(function () use ($program, $data, $actor) {
            unset($data['status'], $data['is_active'], $data['activated_at'], $data['activated_by_core_user_id']);
            $old = $program->only(array_keys($data));
            $program->update(array_merge($data, ['updated_by_core_user_id' => $actor?->core_user_id]));
            $this->audit->record($actor, 'program_updated', $program, $old, $program->only(array_keys($data)));

            return $program->refresh();
        });
    }

    public function ensureDefaultDomains(PkpaProgram $program, ?User $actor = null): void
    {
        $domains = PkpaPracticeDomain::query()
            ->whereIn('code', PkpaPracticeDomain::DEFAULT_CODES)
            ->orderBy('sort_order')
            ->get();

        foreach ($domains as $domain) {
            PkpaProgramDomain::firstOrCreate(
                [
                    'pkpa_program_id' => $program->id,
                    'practice_domain_id' => $domain->id,
                ],
                [
                    'is_required' => true,
                    'selection_mode' => $domain->code === 'PEM' ? 'choose_one' : 'direct',
                    'minimum_option_count' => $domain->code === 'PEM' ? 1 : 0,
                    'sort_order' => $domain->sort_order,
                    'is_active' => true,
                    'created_by_core_user_id' => $actor?->core_user_id,
                    'updated_by_core_user_id' => $actor?->core_user_id,
                ]
            );
        }
    }

    public function updateDomainConfiguration(PkpaProgram $program, array $domains, ?User $actor): void
    {
        DB::transaction(function () use ($program, $domains, $actor) {
            foreach ($domains as $id => $data) {
                $domainConfig = $program->domains()->whereKey($id)->firstOrFail();
                $old = $domainConfig->only([
                    'duration_value',
                    'duration_unit',
                    'minimum_effective_days',
                    'minimum_practice_hours',
                    'weight_percentage',
                    'instructions',
                    'selection_mode',
                    'minimum_option_count',
                ]);

                $domainConfig->update($data + ['updated_by_core_user_id' => $actor?->core_user_id]);
                $this->audit->record($actor, 'program_domain_updated', $domainConfig, $old, $domainConfig->only(array_keys($old)));
            }
        });
    }

    public function changeStatus(PkpaProgram $program, string $status, ?User $actor): PkpaProgram
    {
        if (! in_array($status, PkpaProgram::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Status program tidak valid.']);
        }

        if (in_array($status, ['ready', 'active'], true)) {
            $readiness = $this->readiness($program->loadMissing('domains.practiceDomain.options'));
            if (! $readiness['ready']) {
                throw ValidationException::withMessages(['status' => 'Program belum lengkap: '.implode(' ', $readiness['failed'])]);
            }
        }

        return DB::transaction(function () use ($program, $status, $actor) {
            $old = $program->only(['status', 'is_active', 'activated_at', 'activated_by_core_user_id']);
            $data = [
                'status' => $status,
                'is_active' => $status === 'active',
                'updated_by_core_user_id' => $actor?->core_user_id,
            ];

            if ($status === 'active') {
                $data['activated_at'] = now();
                $data['activated_by_core_user_id'] = $actor?->core_user_id;
            }

            $program->update($data);
            $this->audit->record($actor, $status === 'active' ? 'program_activated' : 'program_status_changed', $program, $old, $program->only(array_keys($old)));

            return $program->refresh();
        });
    }

    public function readiness(PkpaProgram $program): array
    {
        $program->loadMissing('domains.practiceDomain.options');
        $checks = [];

        $defaultCodes = PkpaPracticeDomain::DEFAULT_CODES;
        $activeDefaultDomains = PkpaPracticeDomain::query()->active()->whereIn('code', $defaultCodes)->count();
        $configuredCodes = $program->domains->pluck('practiceDomain.code')->filter()->values();
        $government = PkpaPracticeDomain::query()->where('code', 'PEM')->withCount(['activeOptions'])->first();

        $checks['five_domains_available'] = $activeDefaultDomains === count($defaultCodes);
        $checks['five_program_configs_available'] = $defaultCodes === $configuredCodes->intersect($defaultCodes)->unique()->values()->all();
        $checks['required_duration_valid'] = $program->domains
            ->where('is_active', true)
            ->where('is_required', true)
            ->filter(fn (PkpaProgramDomain $domain) => in_array($domain->practiceDomain?->code, $defaultCodes, true))
            ->every(fn (PkpaProgramDomain $domain) => $domain->isDurationComplete());
        $checks['government_options_valid'] = ($government?->active_options_count ?? 0) >= 2;
        $checks['program_dates_valid'] = ! ($program->start_date && $program->end_date && $program->end_date->lt($program->start_date));
        $checks['no_duplicate_config'] = $program->domains->count() === $program->domains->pluck('practice_domain_id')->unique()->count();

        $labels = [
            'five_domains_available' => 'Lima wahana utama aktif tersedia.',
            'five_program_configs_available' => 'Lima konfigurasi wahana program tersedia.',
            'required_duration_valid' => 'Durasi seluruh wahana wajib sudah valid.',
            'government_options_valid' => 'Pemerintahan mempunyai minimal dua pilihan aktif.',
            'program_dates_valid' => 'Tanggal program valid.',
            'no_duplicate_config' => 'Tidak ada konfigurasi wahana duplikat.',
        ];

        return [
            'checks' => $checks,
            'labels' => $labels,
            'ready' => ! in_array(false, $checks, true),
            'failed' => collect($checks)->filter(fn (bool $passed) => ! $passed)->keys()->map(fn ($key) => $labels[$key])->all(),
        ];
    }
}

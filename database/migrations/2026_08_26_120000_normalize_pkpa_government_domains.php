<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $government = DB::table('pkpa_practice_domains')->where('code', 'PEM')->first();
            if (! $government) {
                return;
            }

            $now = now();

            foreach ([
                ['code' => 'DINKES', 'name' => 'Dinas Kesehatan', 'sort_order' => 10],
                ['code' => 'PUSKESMAS', 'name' => 'Puskesmas', 'sort_order' => 20],
                ['code' => 'LOKAPOM', 'name' => 'Loka BPOM', 'sort_order' => 30],
            ] as $option) {
                $existing = DB::table('pkpa_practice_domain_options')
                    ->where('practice_domain_id', $government->id)
                    ->where('code', $option['code'])
                    ->first();

                if ($existing) {
                    DB::table('pkpa_practice_domain_options')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $option['name'],
                            'is_system' => true,
                            'is_active' => true,
                            'sort_order' => $option['sort_order'],
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('pkpa_practice_domain_options')->insert([
                        'practice_domain_id' => $government->id,
                        'code' => $option['code'],
                        'name' => $option['name'],
                        'description' => null,
                        'is_system' => true,
                        'is_active' => true,
                        'sort_order' => $option['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                }
            }

            $puskesmasOptionId = DB::table('pkpa_practice_domain_options')
                ->where('practice_domain_id', $government->id)
                ->where('code', 'PUSKESMAS')
                ->value('id');

            $legacyPuskesmas = DB::table('pkpa_practice_domains')->where('code', 'PKM')->first();
            if (! $legacyPuskesmas || ! $puskesmasOptionId) {
                return;
            }

            if (Schema::hasTable('pkpa_practice_sites')) {
                DB::table('pkpa_practice_sites')
                    ->where('practice_domain_id', $legacyPuskesmas->id)
                    ->update([
                        'practice_domain_id' => $government->id,
                        'practice_domain_option_id' => $puskesmasOptionId,
                        'updated_at' => $now,
                    ]);
            }

            if (Schema::hasTable('pkpa_program_sites')) {
                DB::table('pkpa_program_sites')
                    ->where('practice_domain_id', $legacyPuskesmas->id)
                    ->update([
                        'practice_domain_id' => $government->id,
                        'practice_domain_option_id' => $puskesmasOptionId,
                        'updated_at' => $now,
                    ]);
            }

            if (Schema::hasTable('pkpa_internal_supervisor_eligibilities')) {
                $legacyEligibilities = DB::table('pkpa_internal_supervisor_eligibilities')
                    ->where('practice_domain_id', $legacyPuskesmas->id)
                    ->orderBy('id')
                    ->get();

                foreach ($legacyEligibilities as $legacyEligibility) {
                    $targetEligibility = DB::table('pkpa_internal_supervisor_eligibilities')
                        ->where('pkpa_program_id', $legacyEligibility->pkpa_program_id)
                        ->where('practice_domain_id', $government->id)
                        ->where('core_user_id', $legacyEligibility->core_user_id)
                        ->where('id', '!=', $legacyEligibility->id)
                        ->orderBy('id')
                        ->first();

                    if (! $targetEligibility) {
                        DB::table('pkpa_internal_supervisor_eligibilities')
                            ->where('id', $legacyEligibility->id)
                            ->update([
                                'practice_domain_id' => $government->id,
                                'updated_at' => $now,
                            ]);

                        continue;
                    }

                    if (Schema::hasTable('pkpa_supervisor_unavailability_periods')) {
                        DB::table('pkpa_supervisor_unavailability_periods')
                            ->where('internal_supervisor_eligibility_id', $legacyEligibility->id)
                            ->update([
                                'internal_supervisor_eligibility_id' => $targetEligibility->id,
                                'updated_at' => $now,
                            ]);
                    }

                    if (Schema::hasTable('pkpa_rotation_assignment_supervisors')) {
                        DB::table('pkpa_rotation_assignment_supervisors')
                            ->where('internal_supervisor_eligibility_id', $legacyEligibility->id)
                            ->update([
                                'internal_supervisor_eligibility_id' => $targetEligibility->id,
                                'updated_at' => $now,
                            ]);
                    }

                    DB::table('pkpa_internal_supervisor_eligibilities')
                        ->where('id', $targetEligibility->id)
                        ->update([
                            'name_snapshot' => $this->preferValue($targetEligibility->name_snapshot ?? null, $legacyEligibility->name_snapshot ?? null),
                            'email_snapshot' => $this->preferValue($targetEligibility->email_snapshot ?? null, $legacyEligibility->email_snapshot ?? null),
                            'lecturer_id_snapshot' => $this->preferValue($targetEligibility->lecturer_id_snapshot ?? null, $legacyEligibility->lecturer_id_snapshot ?? null),
                            'core_account_status_snapshot' => $this->preferActiveStatus($targetEligibility->core_account_status_snapshot ?? null, $legacyEligibility->core_account_status_snapshot ?? null),
                            'role_snapshot' => $this->preferValue($targetEligibility->role_snapshot ?? null, $legacyEligibility->role_snapshot ?? null),
                            'maximum_active_students' => $targetEligibility->maximum_active_students ?? $legacyEligibility->maximum_active_students,
                            'maximum_students_per_program' => $targetEligibility->maximum_students_per_program ?? $legacyEligibility->maximum_students_per_program,
                            'effective_start_date' => $targetEligibility->effective_start_date ?? $legacyEligibility->effective_start_date,
                            'effective_end_date' => $targetEligibility->effective_end_date ?? $legacyEligibility->effective_end_date,
                            'status' => $this->preferEligibilityStatus($targetEligibility->status ?? null, $legacyEligibility->status ?? null),
                            'notes' => $this->preferValue($targetEligibility->notes ?? null, $legacyEligibility->notes ?? null),
                            'last_core_synced_at' => $targetEligibility->last_core_synced_at ?? $legacyEligibility->last_core_synced_at,
                            'last_core_sync_status' => $this->preferValue($targetEligibility->last_core_sync_status ?? null, $legacyEligibility->last_core_sync_status ?? null),
                            'last_core_sync_message' => $this->preferValue($targetEligibility->last_core_sync_message ?? null, $legacyEligibility->last_core_sync_message ?? null),
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ]);

                    DB::table('pkpa_internal_supervisor_eligibilities')
                        ->where('id', $legacyEligibility->id)
                        ->update([
                            'status' => 'inactive',
                            'updated_at' => $now,
                            'deleted_at' => $now,
                        ]);
                }
            }

            if (Schema::hasTable('pkpa_program_domains')) {
                DB::table('pkpa_program_domains')
                    ->where('practice_domain_id', $legacyPuskesmas->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);
            }

            DB::table('pkpa_practice_domains')
                ->where('id', $legacyPuskesmas->id)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);
        });
    }

    public function down(): void
    {
        $legacyPuskesmas = DB::table('pkpa_practice_domains')->where('code', 'PKM')->first();
        if (! $legacyPuskesmas) {
            return;
        }

        DB::table('pkpa_practice_domains')
            ->where('id', $legacyPuskesmas->id)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
    }

    private function preferValue(mixed $primary, mixed $fallback): mixed
    {
        return filled($primary) ? $primary : $fallback;
    }

    private function preferActiveStatus(?string $primary, ?string $fallback): ?string
    {
        return in_array('active', [$primary, $fallback], true)
            ? 'active'
            : ($primary ?: $fallback);
    }

    private function preferEligibilityStatus(?string $primary, ?string $fallback): ?string
    {
        foreach (['active', 'draft', 'suspended', 'expired', 'inactive'] as $status) {
            if ($primary === $status || $fallback === $status) {
                return $status;
            }
        }

        return $primary ?: $fallback;
    }
};

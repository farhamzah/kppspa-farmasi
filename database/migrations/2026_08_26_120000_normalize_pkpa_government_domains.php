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
                DB::table('pkpa_internal_supervisor_eligibilities')
                    ->where('practice_domain_id', $legacyPuskesmas->id)
                    ->update([
                        'practice_domain_id' => $government->id,
                        'updated_at' => $now,
                    ]);
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
};

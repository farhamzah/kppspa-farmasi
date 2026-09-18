<?php

namespace App\Console\Commands;

use App\Models\PkpaProgramDomain;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\PkpaAssessmentSchemeService;
use App\Services\PkpaRotationAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SetupPkpaApotekAssessmentCommand extends Command
{
    protected $signature = 'pkpa:setup-apotek-assessment';

    protected $description = 'Siapkan skema bawaan Panduan PKPA 2026 dan panel penilaian Apotek';

    public function handle(
        PkpaAssessmentSchemeService $schemes,
        PkpaRotationAssessmentService $assessments
    ): int {
        $actor = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['koordinator_kp', 'admin']))
            ->first();
        if (! $actor) {
            $this->error('Admin atau Koordinator PKPA tidak ditemukan.');

            return self::FAILURE;
        }

        $domains = PkpaProgramDomain::query()
            ->with(['practiceDomain', 'activeAssessmentScheme'])
            ->whereHas('practiceDomain', fn ($query) => $query->where('code', 'APT'))
            ->get();
        if ($domains->isEmpty()) {
            $this->warn('Program domain Apotek belum tersedia.');

            return self::SUCCESS;
        }

        $createdSchemes = 0;
        $createdAssessments = 0;
        $existingAssessments = 0;
        foreach ($domains as $domain) {
            $scheme = $domain->activeAssessmentScheme;
            if (! $scheme) {
                $scheme = $schemes->createScheme($domain, [
                    'code' => 'APT-PANDUAN-2026',
                    'name' => 'Penilaian PKPA Apotek - Panduan 2026',
                    'description' => 'Form resmi Preseptor dan Pembimbing Dalam. Masing-masing menghasilkan nilai 0-100; rekap bawaan menggunakan rata-rata 50:50.',
                    'maximum_score' => 100,
                    'rounding_precision' => 2,
                    'rounding_mode' => 'half_up',
                    'require_academic_readiness' => true,
                ], $actor);
                $schemes->saveComponent($scheme, [
                    'code' => 'PRESEPTOR-APT',
                    'name' => 'Penilaian Preseptor Apotek',
                    'component_type' => 'field_supervisor_assessment',
                    'assessor_type' => 'field_supervisor',
                    'calculation_method' => 'direct_score',
                    'weight_percentage' => 50,
                    'maximum_raw_score' => 100,
                    'sort_order' => 10,
                    'status' => 'active',
                    'is_required' => true,
                ], $actor);
                $schemes->saveComponent($scheme, [
                    'code' => 'PEMBIMBING-APT',
                    'name' => 'Penilaian Pembimbing Dalam Apotek',
                    'component_type' => 'internal_supervisor_assessment',
                    'assessor_type' => 'internal_supervisor',
                    'calculation_method' => 'direct_score',
                    'weight_percentage' => 50,
                    'maximum_raw_score' => 100,
                    'sort_order' => 20,
                    'status' => 'active',
                    'is_required' => true,
                ], $actor);
                $scheme = $schemes->activate($scheme, $actor);
                $createdSchemes++;
            }

            $runs = PkpaRotationRun::query()
                ->where('pkpa_program_id', $domain->pkpa_program_id)
                ->where('practice_domain_id', $domain->practice_domain_id)
                ->get();
            foreach ($runs as $run) {
                if ($run->rotationAssessment()->exists()) {
                    $existingAssessments++;

                    continue;
                }
                try {
                    $assessments->createFromRun($run, $actor);
                    $createdAssessments++;
                } catch (ValidationException $exception) {
                    $this->warn($run->studentDisplayName().': '.$exception->getMessage());
                }
            }
        }

        $this->table(['Hasil', 'Jumlah'], [
            ['Skema bawaan dibuat', $createdSchemes],
            ['Panel penilaian dibuat', $createdAssessments],
            ['Panel sudah tersedia', $existingAssessments],
        ]);
        $this->info('Penilaian Apotek sudah ditampilkan. Form tetap terkunci sampai status siap dinilai.');

        return self::SUCCESS;
    }
}

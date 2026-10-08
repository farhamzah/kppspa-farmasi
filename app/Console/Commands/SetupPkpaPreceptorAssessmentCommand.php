<?php

namespace App\Console\Commands;

use App\Models\PkpaProgramDomain;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\PkpaAssessmentSchemeService;
use App\Services\PkpaRotationAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupPkpaPreceptorAssessmentCommand extends Command
{
    protected $signature = 'pkpa:setup-preceptor-assessment {--program= : Kode program PKPA} {--apply : Terapkan setelah memeriksa preview}';

    protected $description = 'Siapkan panel penilaian per wahana tanpa menggabungkan nilai atau mengubah penilaian yang sudah ada';

    public function handle(PkpaAssessmentSchemeService $schemes, PkpaRotationAssessmentService $assessments): int
    {
        if (blank($this->option('program'))) {
            $this->error('Sebutkan --program=KODE agar perubahan terbatas pada program yang dipilih.');

            return self::FAILURE;
        }
        $domains = PkpaProgramDomain::with(['program', 'practiceDomain', 'activeAssessmentScheme'])
            ->when($this->option('program'), fn ($q, $code) => $q->whereHas('program', fn ($p) => $p->where('code', $code)))
            ->whereHas('practiceDomain', fn ($q) => $q->whereIn('code', ['APT', 'PBF', 'RS', 'IND', 'PKM', 'PUSKESMAS', 'PEM']))
            ->get();
        if ($domains->isEmpty()) {
            $this->error('Program/wahana tidak ditemukan.');

            return self::FAILURE;
        }
        $rows = $domains->map(function ($domain) {
            $count = PkpaRotationRun::where('pkpa_program_id', $domain->pkpa_program_id)
                ->where('practice_domain_id', $domain->practice_domain_id)->whereNull('cancelled_at')->whereDoesntHave('rotationAssessment')->count();

            return [$domain->program->code, $domain->practiceDomain->name, $domain->activeAssessmentScheme ? 'Pertahankan skema yang ada' : 'Buat skema nilai terpisah', $count];
        });
        $this->table(['Program', 'Wahana', 'Tindakan', 'Rotasi belum memiliki panel'], $rows->all());
        if (! $this->option('apply')) {
            $this->info('Preview saja. Tidak ada perubahan. Jalankan dengan --apply setelah data diperiksa.');

            return self::SUCCESS;
        }
        $actor = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['koordinator_kp', 'admin']))->first();
        if (! $actor) {
            $this->error('Koordinator/Admin tidak ditemukan.');

            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($domains, $actor, $schemes, $assessments) {
            $created = 0;
            foreach ($domains as $domain) {
                if (! $domain->activeAssessmentScheme) {
                    $code = $domain->practiceDomain->code;
                    $scheme = $schemes->createScheme($domain, [
                        'code' => $code.'-PANDUAN-2026', 'name' => 'Penilaian '.$domain->practiceDomain->name.' - Panduan 2026',
                        'description' => 'Nilai preseptor dan pembimbing disimpan terpisah; penggabungan menunggu ketentuan resmi.',
                        'instructions' => 'SEPARATE_ASSESSOR_RESULTS', 'require_academic_readiness' => true,
                    ], $actor);
                    foreach (['field_supervisor' => 'Preseptor', 'internal_supervisor' => 'Pembimbing Dalam'] as $type => $name) {
                        $schemes->saveComponent($scheme, [
                            'code' => $type.'-'.$code, 'name' => 'Penilaian '.$name,
                            'component_type' => $type.'_assessment', 'assessor_type' => $type,
                            'calculation_method' => 'direct_score', 'weight_percentage' => 0,
                            'maximum_raw_score' => 100, 'is_required' => true, 'status' => 'active',
                        ], $actor);
                    }
                    $schemes->activate($scheme, $actor);
                }
                $runs = PkpaRotationRun::where('pkpa_program_id', $domain->pkpa_program_id)
                    ->where('practice_domain_id', $domain->practice_domain_id)->whereNull('cancelled_at')->whereDoesntHave('rotationAssessment')->get();
                foreach ($runs as $run) {
                    $assessments->createFromRun($run, $actor);
                    $created++;
                }
            }

            return $created;
        });
        $this->info("Panel dibuat: {$created}. Penilaian yang sudah ada tidak diubah. Form terbuka setelah siap dinilai.");

        return self::SUCCESS;
    }
}

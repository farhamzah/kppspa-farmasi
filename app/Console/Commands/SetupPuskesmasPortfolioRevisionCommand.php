<?php

namespace App\Console\Commands;

use App\Models\PkpaPortfolioTemplate;
use App\Models\PkpaProgram;
use App\Models\PkpaRotationPortfolio;
use App\Services\PkpaPortfolioBuilderService;
use App\Support\PkpaPuskesmasPortfolio;
use App\Support\PkpaPuskesmasPortfolioRevision as Revision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupPuskesmasPortfolioRevisionCommand extends Command
{
    protected $signature = 'pkpa:setup-puskesmas-portfolio-revision {--program= : Kode program} {--upgrade-drafts : Pindahkan draf yang belum pernah dikirim dan belum ditandatangani} {--apply : Terapkan hasil preview}';

    protected $description = 'Pasang revisi portofolio Puskesmas tanpa menghapus isian, dokumen, atau nilai lama';

    public function handle(PkpaPortfolioBuilderService $builder): int
    {
        $program = PkpaProgram::where('code', $this->option('program'))->first();
        $base = PkpaPortfolioTemplate::with('sections')->where('code', PkpaPuskesmasPortfolio::TEMPLATE_CODE)->where('status', 'active')->first();
        if (! $program || ! $base) {
            $this->error('Program atau template Puskesmas v1 tidak ditemukan. Sebutkan --program=KODE.');

            return self::FAILURE;
        }
        $source = base_path('docs/referensi-portofolio/'.Revision::reference()['source']);
        if (! is_file($source) || hash_file('sha256', $source) !== Revision::reference()['sha256']) {
            $this->error('Dokumen referensi tidak ditemukan atau checksum berubah. Tidak ada perubahan.');

            return self::FAILURE;
        }
        $candidates = fn () => PkpaRotationPortfolio::where('pkpa_program_id', $program->id)->where('is_current', true)
            ->whereHas('template', fn ($q) => $q->where('code', PkpaPuskesmasPortfolio::TEMPLATE_CODE))
            ->whereHas('rotationRun.practiceDomainOption', fn ($q) => $q->where('code', 'PUSKESMAS'));
        $eligible = fn () => $candidates()->whereIn('status', ['draft', 'in_progress'])->whereNull('submitted_at')
            ->whereNull('locked_at')->whereNull('internal_approved_at')->whereNull('field_verified_at')
            ->whereDoesntHave('signedDocuments')->whereDoesntHave('reviews')->whereDoesntHave('publications');
        $this->table(['Template', 'Draf dapat diperbarui', 'Portofolio lama dipertahankan'], [[Revision::TEMPLATE_CODE, $eligible()->count(), $candidates()->count() - $eligible()->count()]]);
        $this->line('Tujuh aspek, 20 penilaian diri (1-4/N/A), instrumen preseptor dan dosen. Nilai aplikasi tidak diubah.');
        if (! $this->option('apply')) {
            $this->info('Preview saja. Gunakan --apply; tambahkan --upgrade-drafts untuk memperbarui draf yang memenuhi syarat.');

            return self::SUCCESS;
        }
        $updated = DB::transaction(function () use ($base, $program, $eligible, $builder) {
            $template = PkpaPortfolioTemplate::firstOrCreate(['code' => Revision::TEMPLATE_CODE, 'version_number' => 2], [
                'pkpa_program_id' => $program->id, 'practice_domain_id' => $base->practice_domain_id,
                'name' => 'Portofolio PKPA Puskesmas - Revisi November 2026', 'status' => 'active', 'is_current' => true,
                'current_key' => 'PROGRAM:'.$program->id.':'.Revision::TEMPLATE_CODE,
                'integrity_pact' => ['version' => 'PKM-2026-11', 'text' => implode("\n", Revision::reference()['integrity_pact'])],
                'export_configuration' => array_merge($base->export_configuration ?? [], ['reference' => Revision::reference()['source'], 'reference_sha256' => Revision::reference()['sha256']]),
            ]);
            if ((int) $template->pkpa_program_id !== $program->id) {
                throw new \RuntimeException('Revisi ini sudah dipasang untuk program lain. Tidak ada perubahan.');
            }
            foreach (Revision::templateSections() as $index => [$code, $title, $type, $reviewer, $required]) {
                $definition = PkpaPuskesmasPortfolio::editableSections(Revision::TEMPLATE_CODE)[$code] ?? null;
                $original = $base->sections->firstWhere('code', $code);
                $template->sections()->firstOrCreate(['code' => $code], [
                    'title' => $title, 'source_type' => $type, 'reviewer_type' => $reviewer, 'is_required' => $required,
                    'sort_order' => ($index + 1) * 10, 'minimum_items' => $type === 'evidence_gallery' ? 1 : 0,
                    'content_schema' => $definition ? ['fields' => $definition['fields']] : ($original?->content_schema ?? []),
                    'static_content' => in_array($code, ['vision_mission', 'rules'], true) ? implode("\n", Revision::reference()[$code]) : $original?->static_content,
                    'requirement_rules' => ['reference_revision' => '2026-11-01'],
                ]);
            }
            $updated = 0;
            if ($this->option('upgrade-drafts')) {
                foreach ($eligible()->lockForUpdate()->get() as $portfolio) {
                    $portfolio->update(['pkpa_portfolio_template_id' => $template->id]);
                    if (! $portfolio->integrity_acknowledged_at) {
                        $portfolio->update(['integrity_pact_version' => $template->integrity_pact['version'], 'integrity_pact_text' => $template->integrity_pact['text']]);
                    }
                    foreach ($portfolio->sectionRecords()->get() as $record) {
                        $record->update(['completion_snapshot' => array_merge($record->completion_snapshot ?? [], ['template_revision' => ['from' => $base->id, 'to' => $template->id, 'at' => now()->toIso8601String()]])]);
                    }
                    $builder->ensureForRun($portfolio->rotationRun);
                    $updated++;
                }
            }

            return $updated;
        });
        $this->info("Revisi terpasang. Draf diperbarui: {$updated}. Isian lama disimpan sebagai arsip; portofolio terkirim, tanda tangan, ekspor, dan nilai tidak diubah.");

        return self::SUCCESS;
    }
}

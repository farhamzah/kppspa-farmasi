<?php

namespace App\Console\Commands;

use App\Models\PkpaLogbookEntry;
use App\Models\PkpaRotationPortfolio;
use App\Services\PkpaAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SwitchPkpaInternalValidationCommand extends Command
{
    protected $signature = 'pkpa:enable-internal-validation {--apply : Terapkan pemindahan antrean portofolio}';

    protected $description = 'Alihkan kiriman yang menunggu Preseptor ke Pembimbing Dalam untuk semua wahana.';

    public function handle(PkpaAuditService $audit): int
    {
        if (config('my_pkpa.preceptor_document_validation_enabled')) {
            $this->error('Nonaktifkan PKPA_PRECEPTOR_DOCUMENT_VALIDATION_ENABLED terlebih dahulu.');

            return self::FAILURE;
        }
        $query = PkpaRotationPortfolio::whereIn('status', ['submitted_to_field_supervisor', 'field_verified']);
        $this->table(['Data', 'Jumlah'], [
            ['Logbook langsung siap diperiksa Pembimbing Dalam', PkpaLogbookEntry::where('status', 'submitted')->count()],
            ['Portofolio perlu dipindahkan', (clone $query)->count()],
        ]);
        if (! $this->option('apply')) {
            $this->info('Preview. Jalankan dengan --apply untuk memindahkan portofolio. Logbook terkirim sudah otomatis masuk antrean Pembimbing Dalam.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($query, $audit) {
            foreach ($query->orderBy('id')->lockForUpdate()->get() as $portfolio) {
                $old = $portfolio->only('status');
                $portfolio->update(['status' => 'submitted_to_internal_supervisor']);
                $audit->record(null, 'portfolio_routed_to_internal_supervisor', $portfolio, $old, $portfolio->only('status'));
            }
        });
        $this->info('Antrean dipindahkan. Isi dokumen dan riwayat keputusan tetap tersimpan.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\PkpaRotationPortfolio;
use App\Models\User;
use App\Services\PkpaPortfolioBuilderService;
use Illuminate\Console\Command;

class ReopenPkpaPortfolioCommand extends Command
{
    protected $signature = 'pkpa:reopen-portfolio
        {portfolio : ID portofolio yang akan dibuka kembali}
        {--reason= : Alasan pembukaan ulang}
        {--apply : Terapkan pembukaan ulang setelah preview diperiksa}';

    protected $description = 'Membuka kembali portofolio yang telanjur dikirim atau diverifikasi agar mahasiswa dapat melengkapinya.';

    public function handle(PkpaPortfolioBuilderService $portfolios): int
    {
        $portfolio = PkpaRotationPortfolio::with(['rotationRun.enrollment', 'practiceDomain'])->find($this->argument('portfolio'));
        if (! $portfolio) {
            $this->error('Portofolio tidak ditemukan.');

            return self::FAILURE;
        }

        $progress = $portfolios->completeness($portfolio->fresh());
        $this->table(['ID', 'Mahasiswa', 'Wahana', 'Status', 'Catatan belum lengkap'], [[
            $portfolio->id,
            data_get($portfolio->identity_snapshot, 'student_name') ?: $portfolio->rotationRun?->studentDisplayName(),
            $portfolio->practiceDomain?->name ?: '-',
            $portfolio->statusLabel(),
            count($progress['blocking']),
        ]]);

        if (in_array($portfolio->status, ['draft', 'in_progress', 'field_revision_requested', 'internal_revision_requested'], true)) {
            $this->info('Portofolio sudah dapat diedit mahasiswa. Tidak ada perubahan yang diperlukan.');

            return self::SUCCESS;
        }
        if (in_array($portfolio->status, ['published', 'superseded', 'cancelled'], true)) {
            $this->error('Portofolio yang sudah diterbitkan, digantikan, atau dibatalkan tidak dapat dibuka dengan perintah ini.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->warn('Ini hanya preview. Tidak ada data yang diubah. Jalankan ulang dengan --apply untuk membuka kembali.');

            return self::SUCCESS;
        }

        $actor = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['koordinator_kp', 'admin']))
            ->first();
        if (! $actor) {
            $this->error('Akun Koordinator/Admin aktif tidak ditemukan.');

            return self::FAILURE;
        }

        $reason = $this->option('reason') ?: 'Portofolio dibuka kembali karena terverifikasi sebelum seluruh isian lengkap.';
        $portfolios->reopen($portfolio, $reason, $actor);
        $this->info('Portofolio berhasil dibuka kembali. Riwayat pemeriksaan lama tetap tersimpan untuk audit.');

        return self::SUCCESS;
    }
}

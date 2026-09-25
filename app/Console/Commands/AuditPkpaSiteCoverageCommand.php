<?php

namespace App\Console\Commands;

use App\Models\PkpaProgram;
use App\Services\PkpaCapacityReportService;
use Illuminate\Console\Command;

class AuditPkpaSiteCoverageCommand extends Command
{
    protected $signature = 'pkpa:audit-site-coverage {--program= : ID atau kode program PKPA}';

    protected $description = 'Memeriksa perbedaan jumlah master tempat, tempat program, dan tempat berkapasitas tanpa mengubah data';

    public function handle(PkpaCapacityReportService $service): int
    {
        $program = $this->resolveProgram();

        if (! $program) {
            $this->error('Program PKPA tidak ditemukan.');

            return self::FAILURE;
        }

        $coverage = $service->coverage($program);
        $this->info("Cakupan tempat untuk {$program->code} - {$program->name}");
        $this->table(
            ['Wahana', 'Master Tempat', 'Aktif di Program', 'Punya Kapasitas', 'Keterangan'],
            $coverage['rows']->map(fn (array $row) => [
                $row['domain']->name,
                $row['master'],
                $row['program'],
                $row['capacity'],
                $this->rowStatus($row),
            ])->all(),
        );

        foreach ($coverage['rows'] as $row) {
            if ($row['missing_program']->isNotEmpty()) {
                $this->line("Belum masuk program [{$row['domain']->code}]:");
                $row['missing_program']->each(fn (string $name) => $this->line("  - {$name}"));
            }
            if ($row['missing_capacity']->isNotEmpty()) {
                $this->line("Belum punya kapasitas [{$row['domain']->code}]:");
                $row['missing_capacity']->each(fn (string $name) => $this->line("  - {$name}"));
            }
        }

        $this->newLine();
        $this->comment('Audit selesai. Tidak ada data yang diubah.');

        return self::SUCCESS;
    }

    private function resolveProgram(): ?PkpaProgram
    {
        $value = $this->option('program');

        if (filled($value)) {
            return PkpaProgram::query()
                ->where('id', $value)
                ->orWhere('code', $value)
                ->first();
        }

        return PkpaProgram::query()
            ->where('is_active', true)
            ->whereIn('status', ['ready', 'active'])
            ->orderByDesc('id')
            ->first()
            ?? PkpaProgram::query()->whereIn('status', ['ready', 'active'])->orderByDesc('id')->first();
    }

    private function rowStatus(array $row): string
    {
        if ($row['missing_program']->isEmpty() && $row['missing_capacity']->isEmpty()) {
            return 'Lengkap';
        }

        return collect([
            $row['missing_program']->isNotEmpty() ? $row['missing_program']->count().' belum di program' : null,
            $row['missing_capacity']->isNotEmpty() ? $row['missing_capacity']->count().' belum berkapasitas' : null,
        ])->filter()->join('; ');
    }
}

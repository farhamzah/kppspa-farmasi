<?php

namespace App\Console\Commands;

use App\Models\PkpaPublishedAssignment;
use App\Models\PkpaRotationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairPkpaRotationDatesCommand extends Command
{
    protected $signature = 'pkpa:repair-rotation-dates
        {--program= : Batasi ke ID atau kode program PKPA}
        {--apply : Terapkan perbaikan setelah hasil preview diperiksa}';

    protected $description = 'Memulihkan tanggal publikasi dan runtime rotasi dari tanggal asli rancangan penempatan.';

    public function handle(): int
    {
        $program = $this->option('program');
        $assignments = PkpaPublishedAssignment::query()
            ->with(['sourceAssignment', 'publication.program'])
            ->whereNotNull('source_rotation_assignment_id')
            ->when(filled($program), fn ($query) => $query->whereHas('publication.program', fn ($programQuery) => ctype_digit((string) $program)
                ? $programQuery->whereKey((int) $program)
                : $programQuery->where('code', $program)))
            ->get();

        $assignmentChanges = $assignments->filter(function (PkpaPublishedAssignment $assignment) {
            $source = $assignment->sourceAssignment;

            return $source && ($assignment->start_date?->toDateString() !== $source->start_date?->toDateString()
                || $assignment->end_date?->toDateString() !== $source->end_date?->toDateString());
        });

        $runs = PkpaRotationRun::query()
            ->with(['currentAssignment.sourceAssignment', 'currentPortfolio', 'supervisorHistories'])
            ->when(filled($program), fn ($query) => $query->whereHas('program', fn ($programQuery) => ctype_digit((string) $program)
                ? $programQuery->whereKey((int) $program)
                : $programQuery->where('code', $program)))
            ->get();
        $runChanges = $runs->filter(function (PkpaRotationRun $run) {
            $source = $run->currentAssignment?->sourceAssignment;

            return $source && ($run->scheduled_start_date?->toDateString() !== $source->start_date?->toDateString()
                || $run->scheduled_end_date?->toDateString() !== $source->end_date?->toDateString());
        });

        $this->table(
            ['Jenis', 'Jumlah perlu diperbaiki'],
            [
                ['Publikasi penempatan', $assignmentChanges->count()],
                ['Runtime presensi dan logbook', $runChanges->count()],
            ]
        );

        if (! $this->option('apply')) {
            $this->warn('Ini hanya preview. Tidak ada data yang diubah. Jalankan ulang dengan --apply untuk menerapkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($assignmentChanges, $runChanges) {
            foreach ($assignmentChanges as $assignment) {
                $source = $assignment->sourceAssignment;
                $assignment->update([
                    'start_date' => $source->start_date?->toDateString(),
                    'end_date' => $source->end_date?->toDateString(),
                ]);
            }

            foreach ($runChanges as $run) {
                $source = $run->currentAssignment->sourceAssignment;
                $start = $source->start_date?->toDateString();
                $end = $source->end_date?->toDateString();

                $run->update([
                    'scheduled_start_date' => $start,
                    'scheduled_end_date' => $end,
                ]);

                $run->supervisorHistories()
                    ->where('status', 'active')
                    ->update(['effective_end_date' => $end]);
                $run->supervisorHistories()
                    ->where('change_reason', 'Pembentukan runtime awal dari publikasi resmi.')
                    ->update(['effective_start_date' => $start]);

                $portfolio = $run->currentPortfolio;
                if ($portfolio) {
                    $snapshot = $portfolio->placement_snapshot ?? [];
                    $snapshot['start_date'] = $start;
                    $snapshot['end_date'] = $end;
                    $portfolio->update(['placement_snapshot' => $snapshot]);
                }
            }
        });

        $this->info('Tanggal berhasil dipulihkan dari rancangan penempatan. Presensi, logbook, validasi, dan status baca tidak diubah.');

        return self::SUCCESS;
    }
}

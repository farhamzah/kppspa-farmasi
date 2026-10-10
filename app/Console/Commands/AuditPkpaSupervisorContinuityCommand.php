<?php

namespace App\Console\Commands;

use App\Models\PkpaPlacementPublication;
use App\Models\PkpaRotationRun;
use Illuminate\Console\Command;

class AuditPkpaSupervisorContinuityCommand extends Command
{
    protected $signature = 'pkpa:audit-supervisor-continuity {--program=PKPA-2026-G1} {--source-domain=APT}';

    protected $description = 'Audit read-only seluruh mahasiswa dan wahana: publikasi, pembimbing runtime, penilai aktif, dan kesinambungan dari Apotek.';

    public function handle(): int
    {
        $publication = PkpaPlacementPublication::current()->where('status', 'published')
            ->whereHas('program', fn ($q) => $q->where('code', $this->option('program')))
            ->with(['assignments.supervisors', 'assignments.practiceDomain'])->first();
        if (! $publication) {
            $this->error('Publikasi resmi terkini tidak ditemukan. Tidak ada data yang diubah.');

            return self::FAILURE;
        }
        $runs = PkpaRotationRun::where('pkpa_program_id', $publication->pkpa_program_id)
            ->with(['supervisorHistories', 'rotationAssessment.assessors'])->get()
            ->keyBy('pkpa_enrollment_requirement_id');
        $sourceAssignments = $publication->assignments->filter(fn ($a) => $a->practiceDomain?->code === $this->option('source-domain'))
            ->groupBy('pkpa_enrollment_id');
        $rows = [];
        $issues = 0;
        foreach ($publication->assignments->sortBy(fn ($a) => $a->student_name_snapshot.'|'.$a->start_date->toDateString()) as $assignment) {
            $published = $assignment->supervisors->where('supervisor_type', 'internal')->where('status', 'assigned');
            $run = $runs->get($assignment->pkpa_enrollment_requirement_id);
            $active = $run?->supervisorHistories->where('supervisor_type', 'internal')->where('status', 'active') ?? collect();
            $assessors = $run?->rotationAssessment?->assessors->where('assessor_type', 'internal_supervisor')->whereNotIn('status', ['replaced', 'cancelled']) ?? collect();
            $source = $sourceAssignments->get($assignment->pkpa_enrollment_id, collect());
            $sourceIds = $source->flatMap(fn ($a) => $a->supervisors->where('supervisor_type', 'internal')->where('status', 'assigned'))->pluck('core_user_id')->filter()->unique()->sort()->values();
            $publishedIds = $published->pluck('core_user_id')->filter()->unique()->sort()->values();
            $runtimeIds = $active->pluck('core_user_id')->filter()->unique()->sort()->values();
            $problems = [];
            if ($publishedIds->count() !== 1 || $published->count() !== 1) {
                $problems[] = 'Pembimbing resmi kosong/ganda';
            }
            if ($sourceIds->count() !== 1) {
                $problems[] = 'Pembimbing wahana sumber kosong/ganda';
            } elseif ($sourceIds->all() !== $publishedIds->all()) {
                $problems[] = 'Berbeda dari wahana sumber';
            }
            if ($run && $runtimeIds->all() !== $publishedIds->all()) {
                $problems[] = 'Runtime berbeda';
            }
            // Nilai yang sudah dikirim tetap milik dosen asal dan bukan kesalahan pergantian.
            $editableAssessors = $assessors->filter(fn ($a) => ! $a->submitted_at);
            if ($editableAssessors->isNotEmpty() && $editableAssessors->pluck('core_user_id')->unique()->sort()->values()->all() !== $publishedIds->all()) {
                $problems[] = 'Penilai belum selesai berbeda';
            }
            $issues += (int) ($problems !== []);
            $describe = fn ($items) => $items->map(fn ($s) => $s->name_snapshot.' [Core '.$s->core_user_id.']')->unique()->implode('; ') ?: '-';
            $rows[] = [$assignment->student_name_snapshot, $assignment->student_number_snapshot,
                $assignment->practice_domain_name_snapshot, $describe($published), $describe($active),
                $describe($assessors), $problems ? implode('; ', $problems) : 'Sesuai'];
        }
        $this->line('Publikasi: '.$publication->code);
        $this->table(['Mahasiswa', 'NPM', 'Wahana', 'Rekap/resmi', 'Runtime aktif', 'Panel penilai', 'Hasil'], $rows);
        $this->line('Diperiksa: '.count($rows).' penempatan; perlu diperiksa: '.$issues.'. Audit read-only, tidak ada data yang diubah.');

        return $issues ? self::FAILURE : self::SUCCESS;
    }
}

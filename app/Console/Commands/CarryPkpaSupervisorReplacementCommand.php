<?php

namespace App\Console\Commands;

use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaRotationRun;
use App\Models\User;
use App\Services\PkpaPlacementChangeRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarryPkpaSupervisorReplacementCommand extends Command
{
    protected $signature = 'pkpa:carry-supervisor-replacement
        {--program=PKPA-2026-G1 : Kode program}
        {--old-core-id= : Core ID pembimbing lama yang telah diganti}
        {--source-domain=APT : Wahana sumber riwayat pergantian}
        {--apply : Terapkan revisi setelah preview diperiksa}';

    protected $description = 'Meneruskan pergantian pembimbing per mahasiswa ke seluruh penempatan resmi yang masih memakai pembimbing lama.';

    public function handle(PkpaPlacementChangeRequestService $changes): int
    {
        $oldId = (string) $this->option('old-core-id');
        if (blank($oldId)) {
            $this->error('Isi --old-core-id. Tidak ada perubahan yang dibuat.');

            return self::FAILURE;
        }
        $publication = PkpaPlacementPublication::current()
            ->whereHas('program', fn ($q) => $q->where('code', $this->option('program')))
            ->where('status', 'published')->with('assignments.supervisors')->first();
        if (! $publication) {
            $this->error('Publikasi resmi terkini tidak ditemukan.');

            return self::FAILURE;
        }
        $sources = PkpaRotationRun::where('pkpa_program_id', $publication->pkpa_program_id)
            ->whereHas('practiceDomain', fn ($q) => $q->where('code', $this->option('source-domain')))
            ->whereHas('supervisorHistories', fn ($q) => $q->where('supervisor_type', 'internal')->where('core_user_id', $oldId)->where('status', 'ended'))
            ->with('supervisorHistories')->get();
        $rows = collect();
        $errors = [];
        foreach ($publication->assignments as $assignment) {
            $old = $assignment->supervisors->firstWhere('supervisor_type', 'internal');
            if ((string) $old?->core_user_id !== $oldId) {
                continue;
            }
            $candidates = $sources->where('pkpa_enrollment_id', $assignment->pkpa_enrollment_id)
                ->map(fn ($run) => $run->supervisorHistories->first(fn ($h) => $h->supervisor_type === 'internal' && $h->status === 'active' && (string) $h->core_user_id !== $oldId))
                ->filter();
            if ($candidates->count() !== 1 || blank($candidates->first()?->core_user_id)) {
                $errors[] = $assignment->student_name_snapshot.': riwayat pengganti tidak tunggal atau belum tersedia.';
                continue;
            }
            $replacement = $candidates->first();
            $date = $replacement->effective_start_date?->toDateString();
            if (! $date) {
                $errors[] = $assignment->student_name_snapshot.': tanggal pergantian sumber belum tersedia.';
                continue;
            }
            if ($assignment->end_date->toDateString() < $date) {
                continue;
            }
            $eligible = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $publication->pkpa_program_id)
                ->where('practice_domain_id', $assignment->practice_domain_id)
                ->where('core_user_id', $replacement->core_user_id)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('core_account_status_snapshot')->orWhere('core_account_status_snapshot', '!=', 'inactive'))
                ->exists();
            if (! $eligible) {
                $errors[] = $assignment->student_name_snapshot.': pengganti belum aktif untuk '.$assignment->practice_domain_name_snapshot.'.';
            }
            $rows->push([
                'requirement_id' => $assignment->pkpa_enrollment_requirement_id,
                'student' => $assignment->student_name_snapshot,
                'domain' => $assignment->practice_domain_name_snapshot,
                'old' => $old->display_name,
                'new' => $replacement->display_name,
                'core_id' => (string) $replacement->core_user_id,
                'requested_date' => $date,
                'effective_date' => max($date, $assignment->start_date->toDateString()),
            ]);
        }
        $this->table(['Mahasiswa', 'Wahana', 'Pembimbing lama', 'Pengganti', 'Mulai'], $rows->map(fn ($r) => [
            $r['student'], $r['domain'], $r['old'], $r['new'], $r['effective_date'],
        ])->all());
        if ($errors) {
            foreach (array_unique($errors) as $error) {
                $this->error($error);
            }
            $this->error('Preview belum aman. Seluruh perubahan dibatalkan.');

            return self::FAILURE;
        }
        if ($rows->isEmpty()) {
            $this->info('Tidak ada penempatan yang perlu diteruskan. Tidak ada data yang diubah.');

            return self::SUCCESS;
        }
        if (! $this->option('apply')) {
            $this->warn('Ini hanya preview: '.$rows->count().' penempatan. Jalankan dengan --apply setelah diperiksa.');

            return self::SUCCESS;
        }
        $actor = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'koordinator_kp'))->first();
        if (! $actor) {
            $this->error('Koordinator aktif tidak ditemukan. Tidak ada perubahan yang dibuat.');

            return self::FAILURE;
        }
        try {
            $result = DB::transaction(function () use ($publication, $rows, $oldId, $actor, $changes) {
                DB::table('pkpa_programs')->where('id', $publication->pkpa_program_id)->lockForUpdate()->first();
                $current = PkpaPlacementPublication::current()->where('pkpa_program_id', $publication->pkpa_program_id)->lockForUpdate()->firstOrFail();
                if ($current->id !== $publication->id) {
                    throw ValidationException::withMessages(['publication' => 'Publikasi berubah. Jalankan ulang preview.']);
                }
                foreach ($rows->groupBy(fn ($r) => $r['core_id'].'|'.$r['requested_date']) as $group) {
                    $assignments = $current->assignments()->with('supervisors')->whereIn('pkpa_enrollment_requirement_id', $group->pluck('requirement_id'))->lockForUpdate()->get();
                    if ($assignments->count() !== $group->count() || $assignments->contains(fn ($a) => (string) $a->supervisors->firstWhere('supervisor_type', 'internal')?->core_user_id !== $oldId)) {
                        throw ValidationException::withMessages(['assignment' => 'Penugasan berubah. Jalankan ulang preview.']);
                    }
                    $change = $changes->createInternalSupervisorReplacement($current, $assignments->pluck('id')->all(), $group->first()['core_id'], $group->first()['requested_date'], 'Meneruskan pergantian Pembimbing Dalam dari wahana sumber ke seluruh wahana mahasiswa yang sama.', $actor);
                    $changes->submit($change, $actor);
                    $changes->approve($change->refresh(), $actor);
                    $current = $changes->apply($change->refresh(), $actor);
                    if (data_get($change->fresh()->impact_summary, 'runtime_sync.review_required', 0) > 0) {
                        throw ValidationException::withMessages(['runtime' => 'Runtime memerlukan review. Seluruh revisi dibatalkan.']);
                    }
                }

                return $current->code;
            });
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
        $this->info('Pergantian diteruskan untuk '.$rows->count().' penempatan. Publikasi: '.$result);
        $this->info('Riwayat dan penilaian final tetap dipertahankan. Penilaian draf diteruskan untuk ditinjau pengganti.');

        return self::SUCCESS;
    }
}

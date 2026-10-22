<?php

namespace App\Console\Commands;

use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\User;
use App\Services\PkpaPlacementPublicationService;
use App\Services\PkpaPlacementValidationService;
use App\Services\PkpaRotationPublicationSyncService;
use App\Services\PkpaRotationRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublishPkpaPuskesmas2026Command extends Command
{
    protected $signature = 'pkpa:publish-puskesmas-2026 {--apply : Terbitkan setelah preview diperiksa}';

    protected $description = 'Publikasi khusus 36 penempatan Puskesmas, mempertahankan snapshot wahana lain dan kewajiban RS/Industri.';

    public function handle(PkpaPlacementValidationService $validation, PkpaPlacementPublicationService $publications, PkpaRotationPublicationSyncService $sync, PkpaRotationRunService $runs): int
    {
        DB::beginTransaction();
        try {
            $actor = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'koordinator_kp'))->firstOrFail();
            $plan = PkpaPlacementPlan::whereHas('program', fn ($q) => $q->where('code', 'PKPA-2026-G1'))->where('is_current', true)->lockForUpdate()->firstOrFail();
            $source = PkpaPlacementPublication::where('pkpa_program_id', $plan->pkpa_program_id)->where('is_current', true)->where('status', 'published')->lockForUpdate()->firstOrFail();
            if ($this->callSilent('pkpa:plot-puskesmas-2026', ['--program' => 'PKPA-2026-G1']) !== self::SUCCESS) {
                throw ValidationException::withMessages(['roster' => 'Rancangan tidak lagi sesuai preview plotting. Jalankan pkpa:plot-puskesmas-2026 untuk rincian sebelum publikasi.']);
            }
            $rows = $plan->assignments()->where('planning_source', 'puskesmas_roster_2026')->with(['practiceDomain', 'selectedOption', 'supervisors'])->get();
            if ($rows->count() !== 36 || $rows->pluck('pkpa_enrollment_id')->unique()->count() !== 36
                || ! str_starts_with((string) $plan->description, 'PUSKESMAS-2026-11-10-2026-12-07:')
                || $rows->contains(fn ($a) => $a->practiceDomain?->code !== 'PEM' || $a->selectedOption?->code !== 'PUSKESMAS'
                    || $a->start_date?->toDateString() !== '2026-11-10' || $a->end_date?->toDateString() !== '2026-12-07')) {
                throw ValidationException::withMessages(['scope' => 'Rancangan current harus hasil plotting tepat 36 mahasiswa Puskesmas 10 November-07 Desember 2026.']);
            }
            if ((int) data_get($source->summary, 'puskesmas_source_plan_id') === $plan->id) {
                $published = $source->assignments()->with('supervisors')->whereIn('pkpa_enrollment_requirement_id', $rows->pluck('pkpa_enrollment_requirement_id'))->get();
                if ($published->count() !== 36 || ! $rows->every(function ($a) use ($published) {
                    $p = $published->firstWhere('pkpa_enrollment_requirement_id', $a->pkpa_enrollment_requirement_id);

                    return $p && $p->source_rotation_assignment_id === $a->id && $p->practice_site_id === $a->practice_site_id
                        && $p->start_date->equalTo($a->start_date) && $p->end_date->equalTo($a->end_date)
                        && $p->supervisors->where('status', 'assigned')->pluck('source_assignment_supervisor_id')->sort()->values()->all()
                            === $a->supervisors->where('status', 'active')->pluck('id')->sort()->values()->all();
                })) {
                    throw ValidationException::withMessages(['drift' => 'Publikasi Puskesmas sudah ada tetapi rancangan berubah. Gunakan alur revisi, jangan menerbitkan ulang otomatis.']);
                }
                DB::rollBack();
                $this->info('Puskesmas sudah dipublikasikan sesuai rancangan: '.$source->code.'. Tidak ada perubahan.');

                return self::SUCCESS;
            }
            $result = $validation->validatePlan($plan, $actor);
            $futureIds = PkpaEnrollmentRequirement::whereHas('enrollment', fn ($q) => $q->where('pkpa_program_id', $plan->pkpa_program_id))
                ->whereHas('practiceDomain', fn ($q) => $q->whereIn('code', ['RS', 'IND']))->pluck('id');
            $deferred = $result->issues->filter(fn ($i) => $i->severity === 'error' && $i->issue_code === 'ASSIGNMENT_MISSING' && $futureIds->contains($i->pkpa_enrollment_requirement_id));
            $blockers = $result->issues->reject(fn ($i) => $deferred->contains('id', $i->id) || ($i->severity === 'warning' && $i->issue_code === 'SUPERVISOR_LIMIT_MISSING'));
            $this->table(['Rancangan', 'Puskesmas', 'Snapshot wahana lain dipertahankan', 'RS/Industri belum disusun', 'Peringatan beban'], [[
                $plan->code, $rows->count(), $source->assignments()->whereNotIn('pkpa_enrollment_requirement_id', $rows->pluck('pkpa_enrollment_requirement_id'))->count(), $deferred->count(), $result->warning_count,
            ]]);
            $this->warn('Kewajiban RS/Industri tetap ada dan belum dianggap lengkap. Batas beban tidak diisi otomatis.');
            if ($blockers->isNotEmpty()) {
                $this->table(['Kode', 'Temuan penghambat'], $blockers->map(fn ($i) => [$i->issue_code, $i->message])->all());
                throw ValidationException::withMessages(['validation' => 'Ada masalah selain penempatan RS/Industri yang belum disusun. Publikasi dibatalkan.']);
            }
            if (! $this->option('apply')) {
                DB::rollBack();
                $this->info('Preview lulus. Tidak ada data, status rancangan, runtime, atau publikasi yang diubah. Gunakan --apply untuk menerbitkan.');

                return self::SUCCESS;
            }
            $revision = $publications->createPuskesmas2026Revision($source, $plan->fresh(), $rows->pluck('id')->all(), $actor);
            $revision->update(['validation_snapshot' => [
                'scope' => 'Puskesmas 10 November-07 Desember 2026', 'plan_id' => $plan->id,
                'blocking_errors' => 0, 'full_plan_error_count' => $result->error_count,
                'deferred_rs_ind_requirement_ids' => $deferred->pluck('pkpa_enrollment_requirement_id')->all(),
                'supervisor_limit_warnings' => $result->warning_count, 'validated_at' => now()->toDateTimeString(),
            ]]);
            $stats = $sync->sync($revision, $actor);
            if ($stats['review_required'] > 0) {
                throw ValidationException::withMessages(['runtime' => 'Runtime memerlukan pemeriksaan. Seluruh publikasi dibatalkan agar aktivitas lama tetap utuh.']);
            }
            $revision->setRelation('assignments', $revision->assignments()->whereIn('pkpa_enrollment_requirement_id', $rows->pluck('pkpa_enrollment_requirement_id'))->with('supervisors')->get());
            $runtime = $runs->createFromPublication($revision, $actor);
            $revision->unsetRelation('assignments');
            DB::commit();
            $this->info('Puskesmas resmi dipublikasikan: '.$revision->code);
            $this->line('Sinkronisasi: '.json_encode($stats).'; runtime: '.json_encode($runtime));
            $this->info('Snapshot wahana lain dipertahankan. Tidak ada kewajiban RS/Industri yang dihapus atau dinyatakan selesai.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error($exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : 'Proses gagal; periksa log aplikasi. Tidak ada publikasi yang dikonfirmasi berhasil.');

            return self::FAILURE;
        }
    }
}

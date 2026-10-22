<?php

namespace App\Console\Commands;

use App\Models\PkpaEnrollment;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementChangeRequest;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPlacementPublication;
use App\Models\PkpaProgram;
use App\Models\PkpaProgramSite;
use App\Models\User;
use App\Services\PkpaAuditService;
use App\Services\PkpaFieldSupervisorService;
use App\Services\PkpaPlacementPlanService;
use App\Services\PkpaRotationAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlotPkpaPuskesmas2026Command extends Command
{
    protected $signature = 'pkpa:plot-puskesmas-2026 {--program=PKPA-2026-G1} {--apply : Buat rancangan revisi setelah preview diperiksa}';

    protected $description = 'Plotting 36 mahasiswa ke 29 Puskesmas, 10 November-07 Desember 2026; NIM dan pembimbing mengikuti aplikasi.';

    public function handle(PkpaPlacementPlanService $plans, PkpaRotationAssignmentService $assignments, PkpaFieldSupervisorService $fields, PkpaAuditService $audit): int
    {
        $data = json_decode(file_get_contents(database_path('data/pkpa_puskesmas_2026.json')), true, 512, JSON_THROW_ON_ERROR);
        try {
            $prepared = $this->prepare($data);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(PHP_EOL));

            return self::FAILURE;
        }
        $this->table(['Mahasiswa aplikasi', 'NIM aplikasi', 'Puskesmas', 'Pembimbing terakhir', 'Preseptor'], $prepared['rows']->map(fn ($r) => [
            $r['student']->student_name_snapshot, $r['student']->student_number, $r['site']['name'], $r['internal']->display_name, $this->formalName($r['site']),
        ])->all());
        $this->table(['Puskesmas', 'Mahasiswa', 'Preseptor', 'Kontak', 'Ketersediaan'], collect($data['sites'])->map(function ($site) use ($prepared) {
            $group = $prepared['rows']->where('site.key', $site['key']);
            $first = $group->first();

            return [$site['name'], $group->count(), $this->formalName($site), $site['phone'], $first['availability'] ? 'Pakai periode existing' : 'Salin konfigurasi periode lama'];
        })->all());
        $this->line('Periode: '.$data['start_date'].' s.d. '.$data['end_date'].'; 36 mahasiswa, 29 tempat. Rancangan sumber: '.$prepared['plan']->code);
        if ($prepared['already_applied']) {
            $this->info('Plotting sudah sesuai pada rancangan aktif. Tidak ada data yang diubah.');

            return self::SUCCESS;
        }
        if (! $this->option('apply')) {
            $this->warn('Ini hanya preview. Tidak ada data yang diubah. Gunakan --apply untuk membuat rancangan revisi, bukan menerbitkan jadwal.');

            return self::SUCCESS;
        }
        $actor = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'koordinator_kp'))->first();
        if (! $actor) {
            $this->error('Koordinator aktif tidak ditemukan. Tidak ada perubahan yang dibuat.');

            return self::FAILURE;
        }
        try {
            $plan = DB::transaction(function () use ($data, $prepared, $actor, $plans, $assignments, $fields, $audit) {
                PkpaProgram::whereKey($prepared['program']->id)->lockForUpdate()->firstOrFail();
                PkpaPlacementPlan::whereKey($prepared['plan']->id)->lockForUpdate()->firstOrFail();
                $fresh = $this->prepare($data);
                if ($fresh['fingerprint'] !== $prepared['fingerprint']) {
                    throw ValidationException::withMessages(['stale' => 'Sumber plotting berubah. Jalankan ulang preview.']);
                }
                $plan = $plans->clone($fresh['plan'], [
                    'name' => 'Plotting Puskesmas 10 November-07 Desember 2026',
                    'description' => $fresh['marker'], 'copy_assignments' => true,
                ], $actor);
                // Hanya salinan draft target yang diganti; rancangan dan publikasi asal tetap utuh.
                foreach ($plan->assignments()->whereIn('pkpa_enrollment_requirement_id', $fresh['rows']->pluck('requirement.id'))->get() as $copy) {
                    $assignments->deleteDraft($copy, $actor);
                    // Kunci unik juga mencakup soft delete. Hanya salinan baru tanpa publikasi ini yang dibuang.
                    $copy->supervisors()->withTrashed()->forceDelete();
                    $copy->forceDelete();
                }
                $availabilityBySite = [];
                $fieldBySite = [];
                foreach ($fresh['rows'] as $row) {
                    $siteKey = $row['site']['key'];
                    $site = $row['program_site']->practiceSite;
                    if (! isset($availabilityBySite[$siteKey])) {
                        $before = $site->only(['contact_person_name', 'contact_person_phone']);
                        $site->update(['contact_person_name' => $this->formalName($row['site']), 'contact_person_phone' => $row['site']['phone'], 'updated_by_core_user_id' => $actor->core_user_id]);
                        $audit->record($actor, 'puskesmas_preceptor_contact_recorded', $site, $before, $site->only(array_keys($before)));
                        $fieldBySite[$siteKey] = $row['field'] ?: $fields->create($site, [
                            'name_snapshot' => $this->formalName($row['site']), 'position_title' => 'Preseptor PKPA',
                            'is_primary_contact' => true, 'status' => 'active',
                            'notes' => $row['site']['external_number'].'; kontak '.$row['site']['phone'].'; mitra eksternal tanpa akun baru.',
                        ], $actor);
                        $availabilityBySite[$siteKey] = $row['availability'] ?: $row['program_site']->availabilityPeriods()->create(array_merge(
                            $row['template']->only(['minimum_students', 'maximum_students', 'reserved_slots', 'operational_days', 'daily_start_time', 'daily_end_time']),
                            ['start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'status' => 'available', 'notes' => $fresh['marker'], 'created_by_core_user_id' => $actor->core_user_id, 'updated_by_core_user_id' => $actor->core_user_id]
                        ));
                    }
                    $assignments->save($plan, $row['requirement'], [
                        'pkpa_program_site_id' => $row['program_site']->id,
                        'pkpa_site_availability_period_id' => $availabilityBySite[$siteKey]->id,
                        'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
                        'internal_supervisor_eligibility_id' => $row['internal']->id,
                        'site_field_supervisor_id' => $fieldBySite[$siteKey]->id,
                        'planning_source' => 'puskesmas_roster_2026', 'notes' => $fresh['marker'],
                    ], $actor);
                }
                $plans->setCurrent($plan, $actor);
                $audit->record($actor, 'puskesmas_plotting_2026_prepared', $plan, null, ['students' => 36, 'sites' => 29, 'start_date' => $data['start_date'], 'end_date' => $data['end_date']]);

                return $plan;
            });
        } catch (ValidationException $exception) {
            $this->error('Seluruh perubahan di-rollback: '.collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
        $this->info('Plotting tersimpan pada rancangan revisi '.$plan->code.' (ID '.$plan->id.').');
        $this->warn('Belum dipublikasikan. Periksa rancangan di Penyusunan Penempatan, lalu gunakan alur review/publikasi. Apotek/PBF, runtime, logbook, presensi, dan nilai lama tidak diubah.');

        return self::SUCCESS;
    }

    private function prepare(array $data): array
    {
        $fail = fn ($message) => throw ValidationException::withMessages(['plotting' => $message]);
        if (count($data['students']) !== 36 || count($data['sites']) !== 29 || count(array_unique(array_column($data['students'], 'name'))) !== 36) {
            $fail('Sumber harus tepat 36 mahasiswa unik dan 29 Puskesmas.');
        }
        $program = PkpaProgram::where('code', $this->option('program'))->whereIn('status', ['active', 'ready'])->first();
        if (! $program || $program->start_date->toDateString() > $data['start_date'] || $program->end_date->toDateString() < $data['end_date']) {
            $fail('Program aktif atau rentang tanggal program tidak sesuai dengan 10 November-07 Desember 2026.');
        }
        $publication = PkpaPlacementPublication::current()->where('pkpa_program_id', $program->id)->where('status', 'published')->with(['assignments.supervisors', 'assignments.practiceDomain'])->first();
        $currentPlans = PkpaPlacementPlan::where('pkpa_program_id', $program->id)->where('is_current', true)->get();
        if (! $publication || $currentPlans->count() !== 1 || $currentPlans->first()->status === 'archived') {
            $fail('Publikasi resmi dan satu rancangan current wajib tersedia.');
        }
        $plan = $currentPlans->first();
        $students = PkpaEnrollment::where('pkpa_program_id', $program->id)->whereIn('status', ['active', 'on_hold'])->with('requirements')->get();
        $sites = PkpaProgramSite::with(['practiceSite', 'practiceDomain', 'practiceDomainOption', 'programDomain', 'availabilityPeriods', 'practiceSite.fieldSupervisors'])
            ->where('pkpa_program_id', $program->id)->where('is_active', true)->whereIn('status', ['active', 'ready'])->get()
            ->filter(fn ($s) => $s->practiceDomainOption?->code === 'PUSKESMAS' || in_array($s->practiceDomain?->code, ['PKM', 'PUSKESMAS'], true));
        $siteCounts = collect($data['students'])->countBy('site');
        $siteData = collect($data['sites'])->keyBy('key');
        $rows = collect();
        $errors = [];
        foreach ($data['students'] as $entry) {
            try {
                $aliases = collect([$entry['name'], ...($entry['aliases'] ?? [])])->map(fn ($n) => $this->key($n));
                $matches = $students->filter(fn ($s) => $aliases->contains($this->key($s->student_name_snapshot)));
                if ($matches->count() !== 1 || blank($matches->first()->student_number)) {
                    $fail($entry['name'].': nama tidak unik/tidak ditemukan atau NIM aplikasi kosong.');
                }
                $student = $matches->first();
                $site = $siteData->get($entry['site']);
                $siteMatches = $sites->filter(fn ($s) => $this->siteKey($s->practiceSite?->name) === $this->siteKey($site['name']));
                if ($siteMatches->count() !== 1) {
                    $fail($site['name'].': program-tempat Puskesmas aktif tidak ditemukan atau ambigu.');
                }
                $programSite = $siteMatches->first();
                if (! $programSite->practiceSite->is_active || $programSite->practiceSite->status !== 'active' || ! $programSite->programDomain?->is_active) {
                    $fail($site['name'].': tempat/wahana tidak aktif.');
                }
                if ($programSite->practiceSite->cooperation_end_date?->lt(today())) {
                    $fail($site['name'].': kerja sama tempat sudah berakhir.');
                }
                $requirement = $student->requirements->firstWhere('practice_domain_id', $programSite->practice_domain_id);
                if (! $requirement || in_array($requirement->status, ['completed', 'cancelled'], true)) {
                    $fail($entry['name'].': kewajiban wahana tidak tersedia atau sudah selesai/dibatalkan.');
                }
                $handover = PkpaPlacementChangeRequest::where('pkpa_program_id', $program->id)->where('status', 'applied')->where('request_type', 'internal_supervisor_replacement')
                    ->whereJsonContains('impact_summary->student_enrollment_ids', (int) $student->id)->latest('id')->first();
                $coreId = data_get($handover?->impact_summary, 'replacement_core_user_id');
                if (! $coreId) {
                    foreach (['PBF', 'APT'] as $domainCode) {
                        $previous = $publication->assignments->where('pkpa_enrollment_id', $student->id)->filter(fn ($a) => $a->practiceDomain?->code === $domainCode);
                        $advisors = $previous->flatMap->supervisors->where('supervisor_type', 'internal')->where('status', 'assigned')->pluck('core_user_id')->filter()->unique();
                        if ($advisors->count() > 1) {
                            $fail($entry['name'].': pembimbing sebelumnya ambigu.');
                        }
                        if ($advisors->count() === 1) {
                            $coreId = $advisors->first();
                            break;
                        }
                    }
                }
                $eligible = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $program->id)->where('practice_domain_id', $programSite->practice_domain_id)
                    ->where('core_user_id', $coreId)->where('status', 'active')->where(fn ($q) => $q->whereNull('core_account_status_snapshot')->orWhere('core_account_status_snapshot', '!=', 'inactive'))->get();
                if (! $coreId || $eligible->count() !== 1) {
                    $fail($entry['name'].': pembimbing terakhir belum eligible pada wahana Puskesmas.');
                }
                $periods = $programSite->availabilityPeriods->whereIn('status', ['available', 'full']);
                $covering = $periods->filter(fn ($a) => $a->start_date->toDateString() <= $data['start_date'] && $a->end_date->toDateString() >= $data['end_date']);
                $overlap = $periods->filter(fn ($a) => $a->start_date->toDateString() <= $data['end_date'] && $a->end_date->toDateString() >= $data['start_date']);
                if ($covering->count() > 1 || ($covering->isEmpty() && $overlap->isNotEmpty())) {
                    $fail($site['name'].': periode ketersediaan ambigu/tumpang tindih.');
                }
                $availability = $covering->first();
                $template = $availability ?: $periods->sortByDesc('start_date')->first();
                if (! $template || empty($template->operational_days) || $template->plannedAvailableSlots() < $siteCounts[$entry['site']]) {
                    $fail($site['name'].': konfigurasi hari operasional/kapasitas belum mencukupi.');
                }
                $fieldMatches = $programSite->practiceSite->fieldSupervisors->filter(fn ($f) => $this->personKey($f->name_snapshot) === $this->personKey($this->formalName($site)));
                if ($fieldMatches->count() > 1 || ($fieldMatches->isNotEmpty() && $fieldMatches->first()->status !== 'active')) {
                    $fail($site['name'].': profil preseptor ambigu atau nonaktif.');
                }
                $rows->push(compact('student', 'site', 'requirement', 'availability', 'template') + ['program_site' => $programSite, 'internal' => $eligible->first(), 'field' => $fieldMatches->first()]);
            } catch (ValidationException $e) {
                $errors[] = collect($e->errors())->flatten()->implode(' ');
            }
        }
        if ($errors) {
            $fail(implode(PHP_EOL, array_unique($errors)));
        }
        if ($rows->pluck('student.id')->unique()->count() !== 36 || $rows->pluck('student.student_number')->unique()->count() !== 36) {
            $fail('Identitas atau NIM aplikasi tidak unik. Tidak ada pencocokan berdasarkan NIM gambar.');
        }
        $marker = 'PUSKESMAS-2026-11-10-2026-12-07:'.hash('sha256', json_encode($data));
        $already = false;
        if ($plan->description === $marker) {
            $existing = $plan->assignments()->with('supervisors')->whereIn('pkpa_enrollment_requirement_id', $rows->pluck('requirement.id'))->get()->keyBy('pkpa_enrollment_requirement_id');
            $already = $rows->every(function ($r) use ($existing, $data) {
                $a = $existing->get($r['requirement']->id);

                return $a && $a->practice_site_id === $r['program_site']->practice_site_id && $a->start_date->toDateString() === $data['start_date'] && $a->end_date->toDateString() === $data['end_date']
                    && $a->supervisors->contains(fn ($s) => $s->supervisor_type === 'internal' && $s->status === 'active' && (string) $s->core_user_id === (string) $r['internal']->core_user_id)
                    && $a->supervisors->contains(fn ($s) => $s->supervisor_type === 'field' && $s->status === 'active' && $this->personKey($s->name_snapshot) === $this->personKey($this->formalName($r['site'])));
            });
            if (! $already) {
                $fail('Rancangan hasil import sudah diubah. Periksa manual; command tidak menimpa perubahan berikutnya.');
            }
        }
        $fingerprint = hash('sha256', json_encode([$plan->id, $plan->updated_at, $publication->id, $plan->assignments()->with('supervisors')->get()->toArray(), $rows->map(fn ($r) => [$r['student']->id, $r['internal']->id, $r['availability']?->id, $r['field']?->id])->all()]));

        return compact('program', 'publication', 'plan', 'rows', 'marker', 'fingerprint') + ['already_applied' => $already];
    }

    private function key(?string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', Str::ascii((string) $value)));
    }

    private function siteKey(?string $value): string
    {
        return $this->key(preg_replace('/^UPTD?\s+/i', '', trim((string) $value)));
    }

    private function personKey(?string $value): string
    {
        return $this->key(preg_replace('/\b(apt|dr|dra|s|m|farm|si|mm|kes)\b/i', '', (string) $value));
    }

    private function formalName(array $site): string
    {
        return $site['front_title'].' '.$site['preceptor'].', '.$site['back_title'];
    }
}

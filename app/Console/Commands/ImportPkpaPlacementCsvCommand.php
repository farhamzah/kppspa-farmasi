<?php

namespace App\Console\Commands;

use App\Models\PkpaEnrollment;
use App\Models\PkpaEnrollmentRequirement;
use App\Models\PkpaInternalSupervisorEligibility;
use App\Models\PkpaPlacementPlan;
use App\Models\PkpaPracticeDomain;
use App\Models\PkpaProgramSite;
use App\Models\PkpaSiteAvailabilityPeriod;
use App\Models\PkpaSiteFieldSupervisor;
use App\Models\User;
use App\Services\PkpaPlacementPlanService;
use App\Services\PkpaRotationAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportPkpaPlacementCsvCommand extends Command
{
    protected $signature = 'pkpa:import-placement-csv
        {file : Path relatif terhadap storage/app, misalnya imports/PKPA_PBF.csv}
        {--source-plan= : ID rancangan sumber yang akan dikloning}
        {--apply : Buat rancangan revisi dan simpan seluruh plotting setelah preview valid}';

    protected $description = 'Preview atau import plotting PKPA dari CSV ke rancangan revisi baru.';

    private const REQUIRED_HEADERS = [
        'nim', 'nama_mahasiswa', 'nama_wahana', 'jenis_wahana', 'tanggal_mulai', 'tanggal_selesai', 'pembimbing_dalam', 'preseptor', 'catatan',
    ];

    public function handle(PkpaPlacementPlanService $planService, PkpaRotationAssignmentService $assignmentService): int
    {
        $sourcePlanId = (int) $this->option('source-plan');
        $sourcePlan = PkpaPlacementPlan::with('program')->find($sourcePlanId);
        if (! $sourcePlan) {
            $this->error('Rancangan sumber tidak ditemukan. Gunakan --source-plan=ID.');

            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (! Storage::disk('local')->exists($path)) {
            $this->error("Berkas tidak ditemukan pada storage/app/{$path}.");

            return self::FAILURE;
        }

        $rows = $this->readRows(Storage::disk('local')->path($path));
        if (isset($rows['error'])) {
            $this->error($rows['error']);

            return self::FAILURE;
        }

        $resolved = [];
        $errors = [];
        foreach ($rows as $line => $row) {
            try {
                $resolved[] = $this->resolveRow($sourcePlan, $row, $line);
            } catch (ValidationException $exception) {
                $errors[] = 'Baris '.$line.': '.collect($exception->errors())->flatten()->join(' ');
            }
        }

        if ($errors !== []) {
            $this->error('Import dibatalkan. Tidak ada perubahan yang dibuat.');
            foreach ($errors as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        $this->table(['NIM Core', 'Mahasiswa Core', 'Wahana', 'Pembimbing Dalam', 'Preseptor'], collect($resolved)->map(fn (array $item) => [
            $item['enrollment']->student_number, $item['enrollment']->student_name_snapshot, $item['row']['nama_wahana'], $item['internal']->name_snapshot, $item['field']?->name_snapshot ?? 'Belum ditetapkan',
        ])->all());
        $this->info(count($resolved).' baris siap diimpor ke revisi dari '.$sourcePlan->code.'.');

        if (! $this->option('apply')) {
            $this->warn('Ini hanya preview. Jalankan ulang dengan --apply setelah hasilnya disetujui.');

            return self::SUCCESS;
        }

        $actor = User::where('core_user_id', $sourcePlan->updated_by_core_user_id ?: $sourcePlan->created_by_core_user_id)->first();

        try {
            $plan = DB::transaction(function () use ($sourcePlan, $planService, $assignmentService, $resolved, $actor) {
                $plan = $planService->clone($sourcePlan, [
                    'name' => $sourcePlan->name.' (Import CSV)',
                    'description' => 'Revisi otomatis dari '.$sourcePlan->code.' melalui import CSV.',
                    'copy_assignments' => true,
                ], $actor);
                $planService->setCurrent($plan, $actor);

                foreach ($resolved as $item) {
                    $assignmentService->save($plan, $item['requirement'], $item['payload'], $actor);
                }

                return $plan->fresh();
            });
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Import dibatalkan dan seluruh perubahan di-rollback: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Import selesai. Rancangan baru {$plan->code} (ID {$plan->id}) berstatus draft dan menjadi rancangan aktif.");
        $this->warn('Preseptor yang kosong tetap harus dilengkapi sebelum validasi akhir dan publikasi.');

        return self::SUCCESS;
    }

    private function readRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            return ['error' => 'CSV kosong atau tidak dapat dibaca.'];
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        if ($headers !== self::REQUIRED_HEADERS) {
            return ['error' => 'Header CSV tidak sesuai. Gunakan template plotting PKPA yang telah disepakati.'];
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($values) !== count($headers)) {
                return ['error' => "Jumlah kolom tidak sesuai pada baris {$line}."];
            }
            $rows[$line] = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
        }
        fclose($handle);

        return $rows;
    }

    private function resolveRow(PkpaPlacementPlan $plan, array $row, int $line): array
    {
        foreach (['nim', 'nama_mahasiswa', 'nama_wahana', 'jenis_wahana', 'tanggal_mulai', 'tanggal_selesai', 'pembimbing_dalam'] as $key) {
            if (blank($row[$key] ?? null)) {
                throw ValidationException::withMessages([$key => "Kolom {$key} wajib diisi."]);
            }
        }

        $domain = PkpaPracticeDomain::where('code', strtoupper($row['jenis_wahana']))->where('is_active', true)->first();
        $enrollments = PkpaEnrollment::where('pkpa_program_id', $plan->pkpa_program_id)
            ->whereIn('status', ['active', 'on_hold'])
            ->get();
        $enrollment = $enrollments
            ->where('student_number', $row['nim'])
            ->first();
        if (! $enrollment) {
            $nameMatches = $enrollments->filter(fn (PkpaEnrollment $candidate) => $this->personKey($candidate->student_name_snapshot) === $this->personKey($row['nama_mahasiswa']));
            $enrollment = $nameMatches->count() === 1 ? $nameMatches->first() : null;
        }
        $requirement = $enrollment?->requirements()->where('practice_domain_id', $domain?->id)->first();
        $programSites = PkpaProgramSite::with('practiceSite')->where('pkpa_program_id', $plan->pkpa_program_id)
            ->where('practice_domain_id', $domain?->id)->where('is_active', true)->whereIn('status', ['ready', 'active'])->get()
            ->filter(fn (PkpaProgramSite $site) => $this->matches($this->key($site->practiceSite?->name), $this->key($row['nama_wahana'])));
        $internal = PkpaInternalSupervisorEligibility::where('pkpa_program_id', $plan->pkpa_program_id)
            ->where('practice_domain_id', $domain?->id)->where('status', 'active')->get()
            ->first(fn (PkpaInternalSupervisorEligibility $supervisor) => $this->matches($this->personKey($supervisor->name_snapshot), $this->personKey($row['pembimbing_dalam'])));

        if (! $domain || ! $enrollment || ! $requirement || $programSites->count() !== 1 || ! $internal) {
            throw ValidationException::withMessages(['mapping' => 'NIM, wahana, atau Pembimbing Dalam tidak cocok dengan data master aktif.']);
        }
        $site = $programSites->first();
        $availability = $site->availabilityPeriods()->whereIn('status', ['available', 'full'])
            ->whereDate('start_date', '<=', $row['tanggal_mulai'])->whereDate('end_date', '>=', $row['tanggal_selesai'])->first();
        if (! $availability) {
            throw ValidationException::withMessages(['availability' => 'Availability wahana tidak mencakup tanggal pada CSV.']);
        }

        $field = null;
        if (filled($row['preseptor'] ?? null)) {
            $field = PkpaSiteFieldSupervisor::where('practice_site_id', $site->practice_site_id)->where('status', 'active')->get()
                ->first(fn (PkpaSiteFieldSupervisor $supervisor) => $this->matches($this->personKey($supervisor->name_snapshot), $this->personKey($row['preseptor'])));
            if (! $field) {
                throw ValidationException::withMessages(['preseptor' => 'Preseptor pada CSV tidak cocok dengan data master tempat.']);
            }
        }

        return [
            'row' => $row,
            'enrollment' => $enrollment,
            'requirement' => $requirement,
            'internal' => $internal,
            'field' => $field,
            'payload' => [
                'pkpa_program_site_id' => $site->id,
                'pkpa_site_availability_period_id' => $availability->id,
                'start_date' => $row['tanggal_mulai'],
                'end_date' => $row['tanggal_selesai'],
                'internal_supervisor_eligibility_id' => $internal->id,
                'site_field_supervisor_id' => $field?->id,
                'notes' => filled($row['catatan'] ?? null) ? $row['catatan'] : 'Diimpor dari CSV plotting PKPA.',
                'planning_source' => 'csv_import',
            ],
        ];
    }

    private function key(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value));
    }

    private function personKey(?string $value): string
    {
        $ignored = ['apt', 'dr', 's', 'm', 'farm', 'far', 'si', 'kes', 'hum', 'mm', 'ti', 'msc', 'mmrs'];
        $tokens = preg_split('/[^a-z0-9]+/', strtolower((string) $value), -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_values(array_filter($tokens, fn (string $token) => ! in_array($token, $ignored, true))));
    }

    private function matches(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right
            || (min(strlen($left), strlen($right)) >= 8 && (str_starts_with($left, $right) || str_starts_with($right, $left)));
    }
}

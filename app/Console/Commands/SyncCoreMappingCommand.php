<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SyncCoreMappingCommand extends Command
{
    private const SECTIONS = ['users', 'students', 'lecturers', 'field_supervisors'];

    protected $signature = 'kp:sync-core-mapping
        {--dry-run : Preview mapping without writing}
        {--execute : Write mapping columns in the KP database}
        {--confirm-execute : Confirm write to KP mapping columns}
        {--only= : Comma-separated sections: users,students,lecturers,field_supervisors}
        {--show-samples : Show sample planned rows}
        {--report-json : Save mapping report as JSON}';

    protected $description = 'Preview or sync legacy KP rows to imported Core identity IDs';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $report = $this->buildReport($execute);

        $this->renderReport($report);

        if ($this->option('report-json')) {
            $path = $this->writeJsonReport($report);
            $this->info("JSON report written: {$path}");
        }

        if ($execute) {
            if (! $this->option('confirm-execute')) {
                $this->error('Execute refused: missing --confirm-execute.');

                return self::FAILURE;
            }

            if ($report['blockers']) {
                $this->error('Execute refused: blockers must be resolved first.');

                return self::FAILURE;
            }

            $this->applyPlans($report);
            $this->info('Core mapping columns synced.');
        }

        return $report['blockers'] ? self::FAILURE : self::SUCCESS;
    }

    private function buildReport(bool $execute): array
    {
        $only = $this->parseOnly($this->option('only'));

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $execute ? 'execute' : 'dry-run',
            'sections' => $only,
            'counts' => [],
            'planned' => collect(self::SECTIONS)->mapWithKeys(fn (string $section) => [$section => [
                'set' => 0,
                'skip' => 0,
                'blocker' => 0,
                'warning' => 0,
            ]])->all(),
            'warnings' => [],
            'blockers' => [],
            'samples' => [],
            'plans' => [],
        ];

        $this->collectCounts($report);

        if (in_array('users', $only, true)) {
            $this->planUsers($report);
        }

        if (in_array('students', $only, true)) {
            $this->planStudents($report);
        }

        if (in_array('lecturers', $only, true)) {
            $this->planLecturers($report);
        }

        if (in_array('field_supervisors', $only, true)) {
            $this->planFieldSupervisors($report);
        }

        return $report;
    }

    private function collectCounts(array &$report): void
    {
        foreach (self::SECTIONS as $section) {
            $report['counts']['kp'][$section] = DB::table($section)->count();
        }

        foreach ([
            'users',
            'students',
            'lecturers',
            'user_app_accesses',
        ] as $section) {
            $report['counts']['core'][$section] = DB::connection('core')->table($section)->count();
        }

        $report['counts']['core']['kp_app_accesses'] = DB::connection('core')
            ->table('user_app_accesses')
            ->where('app_code', $this->appCode())
            ->count();
    }

    private function planUsers(array &$report): void
    {
        DB::table('users')->orderBy('id')->get()->each(function ($user) use (&$report): void {
            $email = $this->normalize($user->email);
            $coreUser = DB::connection('core')->table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

            if (! $coreUser) {
                $this->mark($report, 'users', 'blocker', $user->id, null, "No Core user found for email {$email}.", ['email' => $email]);
                return;
            }

            $this->planSet($report, 'users', $user, 'core_user_id', $coreUser->id, ['email' => $email, 'core_user_id' => $coreUser->id]);
        });
    }

    private function planStudents(array &$report): void
    {
        DB::table('students')
            ->leftJoin('users', 'users.id', '=', 'students.user_id')
            ->select('students.*', 'users.email as user_email')
            ->orderBy('students.id')
            ->get()
            ->each(function ($student) use (&$report): void {
                $nim = trim((string) $student->nim);
                $email = $this->normalize($student->user_email);

                if ($nim === '') {
                    $this->mark($report, 'students', 'blocker', $student->id, null, 'KP student has empty NIM.', ['kp_student_id' => $student->id]);
                    return;
                }

                $coreStudent = DB::connection('core')->table('students')->whereRaw('LOWER(TRIM(student_number)) = ?', [$this->normalize($nim)])->first();
                if (! $coreStudent) {
                    $this->mark($report, 'students', 'blocker', $student->id, null, "No Core student found for NIM {$nim}.", ['nim' => $nim]);
                    return;
                }

                $coreUser = DB::connection('core')->table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
                if ($coreUser && (int) $coreStudent->user_id !== (int) $coreUser->id) {
                    $this->mark($report, 'students', 'warning', $student->id, $coreStudent->id, "Core student {$nim} user_id does not match Core user from KP email {$email}.", ['nim' => $nim, 'email' => $email]);
                }

                $this->planSet($report, 'students', $student, 'core_student_id', $coreStudent->id, ['nim' => $nim, 'email' => $email, 'core_student_id' => $coreStudent->id]);
            });
    }

    private function planLecturers(array &$report): void
    {
        $seen = [];

        DB::table('lecturers')
            ->leftJoin('users', 'users.id', '=', 'lecturers.user_id')
            ->select('lecturers.*', 'users.email as user_email')
            ->orderBy('lecturers.id')
            ->get()
            ->each(function ($lecturer) use (&$report, &$seen): void {
                $email = $this->normalize($lecturer->user_email);
                $identifier = $this->normalize($lecturer->nidn_nip) ?: $this->normalize($lecturer->employee_number);
                $coreLecturer = null;

                if ($identifier !== '') {
                    $coreLecturer = DB::connection('core')->table('lecturers')->whereRaw('LOWER(TRIM(lecturer_number)) = ?', [$identifier])->first();
                }

                if (! $coreLecturer && $email !== '') {
                    $coreLecturer = DB::connection('core')->table('lecturers')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
                }

                if (! $coreLecturer) {
                    $this->mark($report, 'lecturers', 'blocker', $lecturer->id, null, "No Core lecturer found for lecturer {$lecturer->id}.", ['identifier' => $identifier, 'email' => $email]);
                    return;
                }

                if (isset($seen[$coreLecturer->id]) && $seen[$coreLecturer->id] !== $lecturer->id) {
                    $this->mark($report, 'lecturers', 'blocker', $lecturer->id, $coreLecturer->id, "Core lecturer {$coreLecturer->id} matched multiple KP lecturers.", ['identifier' => $identifier, 'email' => $email]);
                    return;
                }

                $seen[$coreLecturer->id] = $lecturer->id;
                $this->planSet($report, 'lecturers', $lecturer, 'core_lecturer_id', $coreLecturer->id, ['identifier' => $identifier, 'email' => $email, 'core_lecturer_id' => $coreLecturer->id]);
            });
    }

    private function planFieldSupervisors(array &$report): void
    {
        DB::table('field_supervisors')
            ->leftJoin('users', 'users.id', '=', 'field_supervisors.user_id')
            ->select('field_supervisors.*', 'users.email as user_email')
            ->orderBy('field_supervisors.id')
            ->get()
            ->each(function ($fieldSupervisor) use (&$report): void {
                $email = $this->normalize($fieldSupervisor->user_email);
                $coreUser = DB::connection('core')->table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

                if (! $coreUser) {
                    $this->mark($report, 'field_supervisors', 'blocker', $fieldSupervisor->id, null, "No Core user found for field supervisor {$email}.", ['email' => $email]);
                    return;
                }

                $hasAccess = DB::connection('core')->table('user_app_accesses')
                    ->where('user_id', $coreUser->id)
                    ->where('app_code', $this->appCode())
                    ->where('role_slug', 'pembimbing-lapangan')
                    ->where('is_active', true)
                    ->exists();

                if (! $hasAccess) {
                    $this->mark($report, 'field_supervisors', 'blocker', $fieldSupervisor->id, $coreUser->id, "Core field supervisor {$email} lacks active {$this->appCode()} pembimbing-lapangan access.", ['email' => $email]);
                    return;
                }

                $this->planSet($report, 'field_supervisors', $fieldSupervisor, 'core_user_id', $coreUser->id, ['email' => $email, 'core_user_id' => $coreUser->id, 'profile_location' => 'kp']);
            });
    }

    private function planSet(array &$report, string $section, object $row, string $column, int $coreId, array $sample): void
    {
        if ((int) ($row->{$column} ?? 0) === $coreId && ($row->core_sync_status ?? null) === 'synced') {
            $this->mark($report, $section, 'skip', $row->id, $coreId, null, $sample + ['status' => 'already_synced']);
            return;
        }

        $this->mark($report, $section, 'set', $row->id, $coreId, null, $sample);
        $report['plans'][] = [
            'table' => $section,
            'id' => $row->id,
            'column' => $column,
            'core_id' => $coreId,
            'note' => 'Mapped by D4B legacy-to-Core sync.',
        ];
    }

    private function mark(array &$report, string $section, string $action, int|string|null $id, int|string|null $coreId, ?string $message = null, array $sample = []): void
    {
        $report['planned'][$section][$action]++;

        if ($message) {
            $action === 'warning'
                ? $report['warnings'][] = $message
                : $report['blockers'][] = $message;
        }

        if ($this->option('show-samples') && $sample && count($report['samples'][$section] ?? []) < 5) {
            $report['samples'][$section][] = ['action' => $action, 'kp_id' => $id, 'core_id' => $coreId] + $sample;
        }
    }

    private function applyPlans(array $report): void
    {
        DB::transaction(function () use ($report): void {
            foreach ($report['plans'] as $plan) {
                DB::table($plan['table'])
                    ->where('id', $plan['id'])
                    ->update([
                        $plan['column'] => $plan['core_id'],
                        'core_synced_at' => now(),
                        'core_sync_status' => 'synced',
                        'core_sync_note' => $plan['note'],
                    ]);
            }
        });
    }

    private function renderReport(array $report): void
    {
        $this->info('KP legacy-to-Core mapping sync');
        $this->line('Mode: '.($report['mode'] === 'execute' ? 'execute mapping columns only' : 'dry-run only; no writes performed'));

        $this->newLine();
        $this->line('Counts:');
        foreach ($report['counts'] as $group => $counts) {
            $this->line("  {$group}:");
            foreach ($counts as $table => $count) {
                $this->line("    {$table}: {$count}");
            }
        }

        $this->newLine();
        $this->line('Planned mapping:');
        foreach ($report['planned'] as $section => $plan) {
            $this->line(sprintf(
                '  %s: set=%d skip=%d warning=%d blocker=%d',
                $section,
                $plan['set'],
                $plan['skip'],
                $plan['warning'],
                $plan['blocker'],
            ));
        }

        if ($report['samples']) {
            $this->newLine();
            $this->line('Samples:');
            foreach ($report['samples'] as $section => $rows) {
                $this->line("  {$section}:");
                foreach ($rows as $row) {
                    $this->line('    '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $this->newLine();
        $this->line('Warnings:');
        $report['warnings']
            ? collect($report['warnings'])->unique()->each(fn ($warning) => $this->warn("  - {$warning}"))
            : $this->line('  none');

        $this->newLine();
        $this->line('Blockers:');
        $report['blockers']
            ? collect($report['blockers'])->unique()->each(fn ($blocker) => $this->error("  - {$blocker}"))
            : $this->line('  none');
    }

    private function writeJsonReport(array $report): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/kp-core-mapping-sync-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function parseOnly(?string $only): array
    {
        if (! $only) {
            return self::SECTIONS;
        }

        $sections = collect(explode(',', $only))
            ->map(fn (string $section) => trim($section))
            ->filter(fn (string $section) => in_array($section, self::SECTIONS, true))
            ->values()
            ->all();

        return $sections ?: self::SECTIONS;
    }

    private function normalize(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function appCode(): string
    {
        return (string) config('core_farmasi.app_code', 'kppspa-farmasi');
    }
}

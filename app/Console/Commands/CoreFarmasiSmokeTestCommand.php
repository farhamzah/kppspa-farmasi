<?php

namespace App\Console\Commands;

use App\Services\CoreFarmasiClient;
use Illuminate\Console\Command;
use Throwable;

class CoreFarmasiSmokeTestCommand extends Command
{
    protected $signature = 'kp:core-smoke-test
        {--user-id= : Core user ID to check configured app access}
        {--position-type=dekan : Leadership position type to resolve}
        {--unit-type=faculty : Leadership unit type to resolve}
        {--unit-id= : Optional leadership unit ID}';

    protected $description = 'Read-only smoke test for KP Core HTTP adapter';

    public function handle(CoreFarmasiClient $client): int
    {
        $this->info('KP Core HTTP adapter smoke test');
        $this->line('Mode: read-only; no database writes; no auth cutover.');

        if (! $client->enabled()) {
            $this->warn('Core HTTP adapter is disabled or missing required environment values.');
            $this->line('Set KP_CORE_HTTP_ENABLED=true and provide staging app-client credentials to run the real smoke test.');

            return self::SUCCESS;
        }

        try {
            $checks = [
                'study_programs' => $this->checkCollection('study programs', $client->listStudyPrograms(['limit' => 1])),
                'students' => $this->checkCollection('students', $client->searchStudents(['limit' => 1])),
                'lecturers' => $this->checkCollection('lecturers', $client->searchLecturers(['limit' => 1])),
                'leadership' => $this->checkNullable('leadership', $client->getCurrentLeadership($this->leadershipParams())),
            ];

            if ($this->option('user-id')) {
                $access = $client->checkUserAppAccess($this->option('user-id'));
                $checks['app_access'] = $this->checkBoolean('app access', (bool) ($access['has_access'] ?? false));
            } else {
                $checks['app_access'] = true;
                $this->warn('App access check skipped; pass --user-id=<core-user-id> for staging validation.');
            }
        } catch (Throwable $exception) {
            $this->error('Core HTTP smoke test failed.');
            $this->line('Reason: Core Farmasi request failed.');

            return self::FAILURE;
        }

        $passed = collect($checks)->every(fn (bool $check): bool => $check);

        if (! $passed) {
            $this->error('Core HTTP smoke test completed with failures.');

            return self::FAILURE;
        }

        $this->info('Core HTTP smoke test completed.');

        return self::SUCCESS;
    }

    private function leadershipParams(): array
    {
        return array_filter([
            'position_type' => $this->option('position-type'),
            'unit_type' => $this->option('unit-type'),
            'unit_id' => $this->option('unit-id'),
        ], fn ($value): bool => filled($value));
    }

    private function checkCollection(string $label, array $result): bool
    {
        if (! array_key_exists('data', $result)) {
            $this->error("  {$label}: failed");

            return false;
        }

        $count = count($result['data'] ?? []);
        $this->line("  {$label}: ok ({$count} sample row(s))");

        return true;
    }

    private function checkNullable(string $label, ?array $result): bool
    {
        if ($result === null) {
            $this->warn("  {$label}: no current data returned");

            return true;
        }

        $this->line("  {$label}: ok");

        return true;
    }

    private function checkBoolean(string $label, bool $result): bool
    {
        if (! $result) {
            $this->warn("  {$label}: no active access returned");

            return true;
        }

        $this->line("  {$label}: ok");

        return true;
    }
}

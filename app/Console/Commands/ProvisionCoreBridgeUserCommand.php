<?php

namespace App\Console\Commands;

use App\Services\KpCoreBridgeProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProvisionCoreBridgeUserCommand extends Command
{
    protected $signature = 'kp:provision-core-bridge-user
        {--email= : Core user email to provision into KP legacy bridge}
        {--execute : Create/link the KP legacy user and roles}
        {--confirm-execute : Confirm write to KP users/user_roles}
        {--report-json : Save provisioning report as JSON}';

    protected $description = 'Create or sync a KP legacy bridge user from a Core user and Core kp-farmasi app access';

    public function handle(KpCoreBridgeProvisioningService $service): int
    {
        $email = strtolower(trim((string) $this->option('email')));

        if ($email === '') {
            $this->error('Please provide --email.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');

        if ($execute && ! $this->option('confirm-execute')) {
            $this->error('Execute refused: missing --confirm-execute.');

            return self::FAILURE;
        }

        $report = $execute
            ? $service->execute($email)
            : $service->plan($email);

        $report['mode'] = $execute ? 'execute' : 'dry-run';
        $report['generated_at'] = now()->toIso8601String();

        $this->renderReport($report);

        if ($this->option('report-json')) {
            $path = $this->writeJsonReport($report);
            $this->info("JSON report written: {$path}");
        }

        return $report['blockers'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderReport(array $report): void
    {
        $this->info('KP Core bridge user provisioning');
        $this->line('Mode: '.($report['mode'] === 'execute' ? 'execute KP bridge write' : 'dry-run only; no writes performed'));
        $this->line('Email: '.$report['email']);
        $this->line('Action: '.$report['action']);

        $this->newLine();
        $this->line('Core user: '.($report['core_user'] ? 'found' : 'missing'));
        if ($report['core_user']) {
            $this->line('  core_user_id: '.$report['core_user']['id']);
            $this->line('  name: '.$report['core_user']['name']);
            $this->line('  active: '.($report['core_user']['active'] ? 'yes' : 'no'));
            $this->line('  must_change_password: '.($report['core_user']['must_change_password'] ? 'yes' : 'no'));
            $this->line('  Core role candidates: '.($report['core_app_access_roles'] ? implode(', ', $report['core_app_access_roles']) : 'none'));
            $this->line('  mapped KP roles: '.($report['kp_roles'] ? implode(', ', $report['kp_roles']) : 'none'));
        }

        $this->newLine();
        $this->line('Legacy KP user: '.($report['legacy_user_id'] ? 'found/synced' : 'missing'));
        if ($report['legacy_user_id']) {
            $this->line('  legacy_user_id: '.$report['legacy_user_id']);
            $this->line('  status: '.($report['legacy_status'] ?? 'n/a'));
        }

        $this->newLine();
        $this->line('Legacy KP lecturer profile: '.($report['legacy_lecturer_id'] ? 'found/synced' : ($report['core_lecturer'] ? 'will be created' : 'not applicable')));
        if ($report['core_lecturer']) {
            $this->line('  core_lecturer_id: '.$report['core_lecturer']['id']);
            $this->line('  lecturer_number: '.($report['core_lecturer']['lecturer_number'] ?: 'n/a'));
            $this->line('  department: '.($report['core_lecturer']['department_name'] ?: 'n/a'));
            $this->line('  study_program: '.($report['core_lecturer']['study_program_name'] ?: 'n/a'));
        }

        $this->newLine();
        $this->line('Legacy KP field supervisor profile: '.($report['legacy_field_supervisor_id'] ? 'found/synced' : (in_array('pembimbing_lapangan', $report['kp_roles'], true) ? 'will be created' : 'not applicable')));
        if ($report['core_external_person']) {
            $this->line('  core_external_person_id: '.$report['core_external_person']['id']);
            $this->line('  institution: '.($report['core_external_person']['institution_name'] ?: 'n/a'));
            $this->line('  position: '.($report['core_external_person']['position_title'] ?: 'n/a'));
        }

        $this->newLine();
        $this->line('Warnings:');
        $report['warnings']
            ? collect($report['warnings'])->each(fn (string $warning) => $this->warn("  - {$warning}"))
            : $this->line('  none');

        $this->newLine();
        $this->line('Blockers:');
        $report['blockers']
            ? collect($report['blockers'])->each(fn (string $blocker) => $this->error("  - {$blocker}"))
            : $this->line('  none');
    }

    /**
     * @param array<string, mixed> $report
     */
    private function writeJsonReport(array $report): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/kp-core-bridge-user-provision-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}

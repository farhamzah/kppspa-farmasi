<?php

namespace App\Console\Commands;

use App\Services\KpCoreBridgeProvisioningService;
use Illuminate\Console\Command;
use Throwable;

class ProvisionCoreBridgeUsersCommand extends Command
{
    protected $signature = 'kp:provision-core-bridge-users
        {--execute : Create/link KP legacy bridge users and roles}
        {--confirm-execute : Confirm write to KP users/user_roles/profile tables}
        {--limit=0 : Limit number of Core users processed; 0 means no limit}';

    protected $description = 'Bulk dry-run or sync KP legacy bridge users from configured Core app access';

    public function handle(KpCoreBridgeProvisioningService $service): int
    {
        $execute = (bool) $this->option('execute');

        if ($execute && ! $this->option('confirm-execute')) {
            $this->error('Execute refused: missing --confirm-execute.');

            return self::FAILURE;
        }

        try {
            $summary = $service->syncAll($execute, (int) $this->option('limit'));
        } catch (Throwable $exception) {
            $this->error('Core lookup failed safely: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('KP Core bridge bulk provisioning');
        $this->line('Mode: '.$summary['mode']);
        $this->line('Core users with active '.config('core_farmasi.app_code', 'kppspa-farmasi').' access: '.$summary['total']);

        foreach ($summary['rows'] as $row) {
            if ($row['blockers'] !== []) {
                $this->warn("  - {$row['email']}: blocked");
                foreach ($row['blockers'] as $blocker) {
                    $this->warn("    {$blocker}");
                }

                continue;
            }

            $this->line("  - {$row['email']}: {$row['action']}; roles=".implode(',', $row['roles']));
        }

        $this->newLine();
        $this->line('Summary:');
        $this->line('  total: '.$summary['total']);
        $this->line('  created: '.$summary['created']);
        $this->line('  synced: '.$summary['synced']);
        $this->line('  skipped: '.$summary['skipped']);
        $this->line('  blocked: '.$summary['blocked']);

        return $summary['blocked'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

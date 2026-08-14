<?php

namespace App\Console\Commands;

use App\Services\CoreFarmasiClient;
use App\Services\CoreHttpUserProjectionService;
use App\Support\CoreRoleTranslator;
use Illuminate\Console\Command;
use Throwable;

class SyncCoreHttpAppUsersCommand extends Command
{
    protected $signature = 'kp:sync-core-http-app-users
        {--execute : Create/update local KPPSPA users, roles, and profiles}
        {--confirm-execute : Confirm write to local users/user_roles/profile tables}
        {--limit=100 : Maximum Core app-access users to process}
        {--role= : Optional Core app role slug filter}';

    protected $description = 'Sync local KPPSPA user/profile projections from Core HTTP app access.';

    public function handle(CoreFarmasiClient $client, CoreHttpUserProjectionService $projection): int
    {
        $execute = (bool) $this->option('execute');

        if ($execute && ! $this->option('confirm-execute')) {
            $this->error('Execute refused: missing --confirm-execute.');

            return self::FAILURE;
        }

        if (! $client->enabled()) {
            $this->warn('Core HTTP adapter is disabled or missing required environment values.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;
        $synced = 0;
        $blocked = 0;
        $warnings = 0;
        $page = 1;

        $this->info('KPPSPA Core HTTP app-user sync');
        $this->line('Mode: '.($execute ? 'execute local projection writes' : 'dry-run only; no writes performed'));

        try {
            do {
                $batch = $client->listAppAccessUsers(array_filter([
                    'limit' => min(100, $limit - $processed),
                    'page' => $page,
                    'role' => $this->option('role') ?: null,
                ]));

                $rows = $batch['data'] ?? [];
                foreach ($rows as $row) {
                    if ($processed >= $limit) {
                        break 2;
                    }

                    $processed++;
                    $coreUser = is_array($row['user'] ?? null) ? $row['user'] : [];
                    $roles = is_iterable($row['roles'] ?? null) ? $row['roles'] : [];
                    $kpRoles = CoreRoleTranslator::coreRolesToKp($roles);
                    $email = $coreUser['email'] ?? 'unknown-email';

                    if ($kpRoles === []) {
                        $blocked++;
                        $this->warn("  - {$email}: blocked; no supported KPPSPA role");
                        continue;
                    }

                    if (! $execute) {
                        $this->line("  - {$email}: ready; roles=".implode(',', $kpRoles));
                        continue;
                    }

                    $result = $projection->project($coreUser, [
                        'has_access' => true,
                        'app_code' => config('core_farmasi.app_code', 'kppspa-farmasi'),
                        'user_id' => $coreUser['id'] ?? null,
                        'roles' => $row['roles'] ?? [],
                    ], $row);

                    if (! ($result['ok'] ?? false)) {
                        $blocked++;
                        $this->warn("  - {$email}: blocked; ".implode(' ', $result['blockers'] ?? []));
                        continue;
                    }

                    if (($result['warnings'] ?? []) !== []) {
                        $warnings++;
                        $this->warn("  - {$email}: synced with warning; ".implode(' ', $result['warnings']));
                    } else {
                        $this->line("  - {$email}: synced; roles=".implode(',', $result['kp_roles'] ?? []));
                    }

                    $synced++;
                }

                $hasMore = (bool) data_get($batch, 'meta.has_more', false);
                $page++;
            } while ($hasMore && $processed < $limit);
        } catch (Throwable $exception) {
            $this->error('Core HTTP app-user sync failed safely.');
            $this->line('Reason: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Summary:');
        $this->line('  processed: '.$processed);
        $this->line('  synced: '.$synced);
        $this->line('  warnings: '.$warnings);
        $this->line('  blocked: '.$blocked);

        return $blocked > 0 ? self::FAILURE : self::SUCCESS;
    }
}

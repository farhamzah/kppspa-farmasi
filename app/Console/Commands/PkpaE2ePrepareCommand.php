<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PkpaE2ePrepareCommand extends Command
{
    protected $signature = 'pkpa:e2e-prepare {--force : Confirm fixture writes outside testing/local/staging}';

    protected $description = 'Prepare safe MY PSPA browser E2E fixtures without creating production bypass routes.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Blocked: pkpa:e2e-prepare is not allowed in production.');

            return self::FAILURE;
        }

        $emails = [
            'admin@sikp.test',
            'koordinator@sikp.test',
            'mahasiswa@sikp.test',
            'dosen@sikp.test',
            'lapangan@sikp.test',
        ];

        $updated = User::query()
            ->whereIn('email', $emails)
            ->update([
                'must_change_password' => false,
                'profile_completed' => true,
                'status' => 'active',
            ]);

        $this->info("Prepared {$updated} E2E fixture users.");

        return self::SUCCESS;
    }
}

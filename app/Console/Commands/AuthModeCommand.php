<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuthModeCommand extends Command
{
    protected $signature = 'kp:auth-mode';

    protected $description = 'Show the current KP authentication mode';

    public function handle(): int
    {
        $mode = config('kp_auth.mode', 'legacy');
        $allowed = ['legacy', 'core_http', 'core_bridge', 'core_bridge_with_legacy_fallback'];

        $this->info('KP auth mode');
        $this->line('Current mode: '.$mode);
        $this->line('Allowed modes: '.implode(', ', $allowed));

        if ($mode !== 'legacy') {
            $this->warn('Warning: KP auth mode is not legacy. Confirm this is intentional for the current environment.');
        }

        return in_array($mode, $allowed, true) ? self::SUCCESS : self::FAILURE;
    }
}

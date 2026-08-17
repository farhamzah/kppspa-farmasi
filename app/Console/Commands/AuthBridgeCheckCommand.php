<?php

namespace App\Console\Commands;

use App\Models\Core\CoreUser;
use App\Models\User;
use Illuminate\Console\Command;

class AuthBridgeCheckCommand extends Command
{
    protected $signature = 'kp:auth-bridge-check {--email= : Email to inspect}';

    protected $description = 'Read-only diagnostic for KP Core bridge authentication readiness';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        if ($email === '') {
            $this->error('Please provide --email.');

            return self::FAILURE;
        }

        $this->info('KP Core auth bridge diagnostic');
        $this->line('Mode: read-only; password is not checked.');
        $this->line('Auth mode: '.config('kp_auth.mode', 'legacy'));

        $coreUser = CoreUser::query()
            ->with(['roles', 'appAccesses'])
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        $this->newLine();
        $this->line('Core user: '.($coreUser ? 'found' : 'missing'));

        if (! $coreUser) {
            return self::FAILURE;
        }

        $coreRoles = $coreUser->roles->pluck('name')->values()->all();
        $appCode = (string) config('core_farmasi.app_code', 'kppspa-farmasi');
        $kpAccesses = $coreUser->appAccesses
            ->where('app_code', $appCode)
            ->where('is_active', true)
            ->pluck('role_slug')
            ->values()
            ->all();
        $legacyUser = User::query()->where('core_user_id', $coreUser->id)->first();

        $this->line('  core_user_id: '.$coreUser->id);
        $this->line('  active: '.($coreUser->active ? 'yes' : 'no'));
        $this->line('  must_change_password: '.($coreUser->must_change_password ? 'yes' : 'no'));
        $this->line('  Core roles: '.($coreRoles ? implode(', ', $coreRoles) : 'none'));
        $this->line("  {$appCode} app access roles: ".($kpAccesses ? implode(', ', $kpAccesses) : 'none'));
        $this->line('  has admin-core: '.(in_array('admin-core', $coreRoles, true) || in_array('admin-core', $kpAccesses, true) ? 'yes' : 'no'));

        $this->newLine();
        $this->line('Legacy bridge user: '.($legacyUser ? 'found' : 'missing'));
        if ($legacyUser) {
            $this->line('  legacy_user_id: '.$legacyUser->id);
            $this->line('  status: '.$legacyUser->status);
            $this->line('  roles: '.($legacyUser->roles()->pluck('name')->implode(', ') ?: 'none'));
        }

        if (! $coreUser->active || $coreUser->must_change_password || ! $legacyUser || $legacyUser->status !== 'active' || ! $kpAccesses) {
            $this->error('Auth bridge diagnostic failed.');

            return self::FAILURE;
        }

        $this->info('Auth bridge diagnostic passed.');

        return self::SUCCESS;
    }
}

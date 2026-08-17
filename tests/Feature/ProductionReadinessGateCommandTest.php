<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessGateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_readiness_gate_reports_local_blockers_without_writes(): void
    {
        $this->artisan('kp:production-readiness-gate')
            ->expectsOutputToContain('KP production readiness gate')
            ->expectsOutputToContain('Dry run: yes')
            ->expectsOutputToContain('External request sent: no')
            ->expectsOutputToContain('Write to Core/TU/SAFA: no/no/no')
            ->expectsOutputToContain('Ready for production: no')
            ->expectsOutputToContain('Ready for runtime TU bridge: no')
            ->assertFailed();
    }

    public function test_production_readiness_gate_can_pass_required_production_checks_but_keeps_tu_runtime_closed(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.url', 'https://kp-farmasi.example.test');
        config()->set('app.key', 'base64:'.str_repeat('a', 44));
        config()->set('session.secure', true);
        config()->set('queue.default', 'database');
        config()->set('cache.default', 'array');
        config()->set('mail.default', 'smtp');
        config()->set('kp_auth.mode', 'core_http');
        config()->set('kp_master_data.read_mode', 'core_preferred');
        config()->set('core_farmasi.read_mode', 'core_preferred');
        config()->set('core_farmasi.enabled', true);
        config()->set('core_farmasi.verify_ssl', true);
        config()->set('services.tu_farmasi.endpoint', null);
        config()->set('services.safa.endpoint', null);

        $this->artisan('kp:production-readiness-gate')
            ->expectsOutputToContain('Ready for production: yes')
            ->expectsOutputToContain('Ready for runtime TU bridge: no')
            ->expectsOutputToContain('Read-only counts unchanged: yes')
            ->assertSuccessful();
    }

    public function test_production_readiness_gate_blocks_core_auth_with_legacy_master_data(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.url', 'https://kp-farmasi.example.test');
        config()->set('app.key', 'base64:'.str_repeat('a', 44));
        config()->set('session.secure', true);
        config()->set('queue.default', 'database');
        config()->set('cache.default', 'array');
        config()->set('mail.default', 'smtp');
        config()->set('kp_auth.mode', 'core_http');
        config()->set('kp_master_data.read_mode', 'legacy');
        config()->set('core_farmasi.read_mode', 'legacy');
        config()->set('services.tu_farmasi.endpoint', null);
        config()->set('services.safa.endpoint', null);

        $this->artisan('kp:production-readiness-gate')
            ->expectsOutputToContain('master_data_core_bridge_aligned')
            ->expectsOutputToContain('Ready for production: no')
            ->assertFailed();
    }
}

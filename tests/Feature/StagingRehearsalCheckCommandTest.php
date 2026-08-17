<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingRehearsalCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_rehearsal_check_is_read_only_and_reports_required_sections(): void
    {
        config()->set('app.debug', false);
        config()->set('app.url', 'https://kp-farmasi-staging.example.test');
        config()->set('queue.default', 'database');
        config()->set('mail.default', 'smtp');
        config()->set('kp_auth.mode', 'core_http');
        config()->set('kp_master_data.read_mode', 'core_preferred');
        config()->set('services.tu_farmasi.endpoint', null);
        config()->set('services.safa.endpoint', null);

        $this->artisan('kp:staging-rehearsal-check')
            ->expectsOutputToContain('KP staging rehearsal check')
            ->expectsOutputToContain('Dry run: yes')
            ->expectsOutputToContain('External request sent: no')
            ->expectsOutputToContain('Write to Core/TU/SAFA: no/no/no')
            ->expectsOutputToContain('Ready for runtime TU bridge: no')
            ->expectsOutputToContain('Read-only counts unchanged: yes')
            ->assertSuccessful();
    }

    public function test_staging_rehearsal_check_blocks_when_tu_runtime_endpoint_is_enabled(): void
    {
        config()->set('services.tu_farmasi.endpoint', 'https://tu.example.test/api/documents');

        $this->artisan('kp:staging-rehearsal-check')
            ->expectsOutputToContain('Ready for staging rehearsal: no')
            ->expectsOutputToContain('tu_runtime_bridge_closed')
            ->assertFailed();
    }

    public function test_staging_rehearsal_check_blocks_core_auth_with_legacy_master_data(): void
    {
        config()->set('app.debug', false);
        config()->set('app.url', 'https://kp-farmasi-staging.example.test');
        config()->set('queue.default', 'database');
        config()->set('mail.default', 'smtp');
        config()->set('kp_auth.mode', 'core_http');
        config()->set('kp_master_data.read_mode', 'legacy');
        config()->set('services.tu_farmasi.endpoint', null);
        config()->set('services.safa.endpoint', null);

        $this->artisan('kp:staging-rehearsal-check')
            ->expectsOutputToContain('Ready for staging rehearsal: no')
            ->expectsOutputToContain('master_data_core_bridge_aligned')
            ->assertFailed();
    }
}

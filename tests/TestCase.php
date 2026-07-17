<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('kp_auth.mode', 'legacy');
        config()->set('app.name', 'MY PSPA');
        config()->set('my_pspa.local_account_management_enabled', true);
        config()->set('my_pspa.student_place_selection_enabled', true);
        config()->set('core_farmasi.app_code', 'kp-farmasi');
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('kp:sync-core-http-app-users --execute --confirm-execute --limit=500')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

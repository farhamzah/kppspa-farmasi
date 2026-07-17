<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('pkpa-downloads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('pkpa-exports', fn (Request $request) => Limit::perMinute(12)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('pkpa-health', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}

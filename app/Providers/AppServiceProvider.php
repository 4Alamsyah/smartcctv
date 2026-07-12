<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Public citizen-facing endpoint, no auth: keyed by IP to blunt spam
        // without requiring a CAPTCHA for the MVP.
        RateLimiter::for('crm-reports', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}

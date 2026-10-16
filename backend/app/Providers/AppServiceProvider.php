<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
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
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(Config::integer('api.rate_limit_per_minute'))
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Each try checks the current password, so a stolen session cannot guess it at the general API rate
        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(Config::integer('auth.throttle.password_change_per_minute'))
            ->by((string) $request->user()?->getAuthIdentifier()));
    }
}

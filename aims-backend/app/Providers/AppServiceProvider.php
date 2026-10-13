<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request): array {
            $login = Str::lower(trim((string) $request->input('login')));

            return [
                Limit::perMinute(60)->by('login-requests|'.$request->ip()),
                Limit::perMinute(5)
                    ->by('login-failures|'.$login.'|'.$request->ip())
                    ->after(fn ($response) => $response->getStatusCode() === 422),
            ];
        });

        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('enrollment', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('recovery', function (Request $request): array {
            $identifier = Str::lower(trim((string) $request->input('user_id')));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by($request->path().'|'.$identifier.'|'.$request->ip()),
            ];
        });
    }
}

<?php

namespace App\Providers;

use App\Policies\PersonalAccessTokenPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

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
        Gate::policy(PersonalAccessToken::class, PersonalAccessTokenPolicy::class);

        RateLimiter::for('admin-login', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(20)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('credentials:'.hash('sha256', $email.'|'.$request->ip())),
            ];
        });

        RateLimiter::for('admin-api', fn (Request $request): Limit => Limit::perMinute(120)->by('admin:'.$request->user()->getAuthIdentifier()));
    }
}

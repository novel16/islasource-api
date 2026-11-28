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

        RateLimiter::for('admin-password', fn (Request $request): Limit => Limit::perMinute(5)->by('admin:'.$request->user()->getAuthIdentifier()));

        RateLimiter::for('admin-heavy', fn (Request $request): Limit => Limit::perMinute(20)->by('admin:'.$request->user()->getAuthIdentifier()));

        RateLimiter::for('public-catalog', fn (Request $request): Limit => Limit::perMinute(300)->by('ip:'.$request->ip()));

        RateLimiter::for('quote-otp', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            $limits = [Limit::perMinute(5)->by('ip:'.$request->ip())];
            if ($email !== '') {
                $limits[] = Limit::perMinutes(10, 3)->by('email:'.hash('sha256', $email));
            }

            return $limits;
        });

        RateLimiter::for('quote-submit', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            $limits = [Limit::perMinute(6)->by('ip:'.$request->ip())];
            if ($email !== '') {
                $limits[] = Limit::perMinute(3)
                    ->by('email:'.hash('sha256', $email))
                    ->after(fn ($response): bool => $response->getStatusCode() < 400);
            }

            return $limits;
        });

        RateLimiter::for('guest-read', fn (Request $request): Limit => Limit::perMinute(30)->by(
            'ip:'.$request->ip().'|token:'.hash('sha256', (string) $request->route('token'))
        ));

        RateLimiter::for('guest-action', fn (Request $request): Limit => Limit::perMinute(10)->by(
            'ip:'.$request->ip().'|token:'.hash('sha256', (string) $request->route('token'))
        ));

        RateLimiter::for('paymongo-webhook', fn (Request $request): Limit => Limit::perMinute(120)->by('ip:'.$request->ip()));
    }
}

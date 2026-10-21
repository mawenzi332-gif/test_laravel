<?php

namespace App\Providers;

use App\Auth\CreateUser;
use App\Auth\ResetUserPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->singleton(CreatesNewUsers::class, CreateUser::class);
        $this->app->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return url('/reset-password/'.$token).'?'.http_build_query([
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        RateLimiter::for('registration', function (Request $request): Limit {
            return Limit::perMinutes(
                config('account-protection.rate_limits.registration.decay_minutes'),
                config('account-protection.rate_limits.registration.attempts')
            )->by('registration:'.$request->ip());
        });

        RateLimiter::for('password-recovery', function (Request $request): Limit {
            return Limit::perMinutes(
                config('account-protection.rate_limits.password_recovery.decay_minutes'),
                config('account-protection.rate_limits.password_recovery.attempts')
            )->by('password-recovery:'.strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinutes(
                config('account-protection.rate_limits.login.decay_minutes'),
                config('account-protection.rate_limits.login.attempts')
            )->by('login:'.strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('comment-publication', function (Request $request): Limit {
            return Limit::perMinutes(
                config('account-protection.rate_limits.comment.decay_minutes'),
                config('account-protection.rate_limits.comment.attempts')
            )->by('comment-publication:'.$request->ip());
        });
    }
}

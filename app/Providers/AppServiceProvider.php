<?php

namespace App\Providers;

use App\Support\ConfigValidator;
use App\Support\SecurityLog;
use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Web requests refuse to run with an insecure configuration. Console commands are skipped so that
        // `key:generate` / `vault:key` can still fix it; the tests call the validator directly.
        if (! $this->app->runningInConsole()) {
            ConfigValidator::assertValid();
        }

        $this->logAuthenticationEvents();
        $this->logAuthorizationDenials();

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
    }

    private function logAuthenticationEvents(): void
    {
        Event::listen(Login::class, fn (Login $e) => SecurityLog::event('auth.login.success', SecurityLog::AUTH, ['target_user_id' => $e->user->getAuthIdentifier()]));
        Event::listen(Logout::class, fn (Logout $e) => SecurityLog::event('auth.logout', SecurityLog::AUTH, ['target_user_id' => $e->user?->getAuthIdentifier()]));
        Event::listen(Registered::class, fn (Registered $e) => SecurityLog::event('auth.registered', SecurityLog::AUTH, ['target_user_id' => $e->user->getAuthIdentifier()]));
        // The e-mail is logged on failure (needed to spot credential stuffing against one account); the password never is.
        Event::listen(Failed::class, fn (Failed $e) => SecurityLog::event('auth.login.failed', SecurityLog::AUTH, [
            'outcome' => 'failure',
            'email' => $e->credentials['email'] ?? null,
            'known_user' => $e->user !== null,
        ], 'warning'));
        Event::listen(Lockout::class, fn (Lockout $e) => SecurityLog::event('auth.lockout', SecurityLog::AUTH, [
            'outcome' => 'failure',
            'email' => mb_strtolower((string) $e->request->input('email')),
        ], 'alert'));
    }

    private function logAuthorizationDenials(): void
    {
        Gate::after(function ($user, string $ability, $result, array $arguments) {
            $denied = $result === false || ($result instanceof GateResponse && $result->denied());
            if ($denied) {
                $target = $arguments[0] ?? null;
                SecurityLog::event('authz.denied', SecurityLog::AUTHZ, [
                    'outcome' => 'failure',
                    'ability' => $ability,
                    'resource' => is_object($target) ? class_basename($target) : null,
                    'resource_id' => is_object($target) ? ($target->id ?? null) : null,
                ], 'warning');
            }

            return null;
        });
    }
}

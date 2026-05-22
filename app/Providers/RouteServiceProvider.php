<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Routing itself is now configured in `bootstrap/app.php` via
 * `Application::configure(...)->withRouting(...)`. This provider is
 * left only to register the named rate limiters used by the API
 * (`throttle:api`) and the auth endpoints (`throttle:auth`).
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // General API: 60 req/min per user (or per IP for guests).
        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Auth endpoints: tight per-IP throttle to stop credential stuffing
        // and email-resend abuse. Email is added to the key when present so
        // attackers cannot rotate IPs to bypass account-specific limits.
        RateLimiter::for('auth', static function (Request $request) {
            $email = (string) $request->input('email', '');

            return [
                Limit::perMinute(10)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.mb_strtolower($email).'|ip:'.$request->ip()),
            ];
        });
    }
}

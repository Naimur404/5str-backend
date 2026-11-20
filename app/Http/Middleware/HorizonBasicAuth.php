<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-aware gate for the Horizon dashboard.
 *
 * Pairs with the Blade `/horizon-login` view + HorizonAuthController.
 * Flow on production:
 *
 *   1. Optional IP allowlist — if HORIZON_IP_ALLOWLIST is set, any
 *      request from outside the list 404s before doing anything
 *      else, so scanners can't even confirm the dashboard exists.
 *
 *   2. Session check — if the visitor isn't logged into the `web`
 *      guard, we redirect to /horizon-login (carrying the original
 *      URL via the `intended` flash). After they log in we send
 *      them right back.
 *
 *   3. Role check — the `viewHorizon` Gate in
 *      HorizonServiceProvider then enforces admin / super-admin
 *      role membership.
 *
 * No shared secret. The credential is the user's existing admin
 * password; rotating that password rotates Horizon access.
 */
class HorizonBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->ipAllowed($request)) {
            // Pretend the dashboard isn't here at all.
            abort(404);
        }

        if (! Auth::guard('web')->check()) {
            // Stash the URL the user wanted (e.g. /horizon/jobs) so we
            // can bounce back after a successful login.
            session()->put('url.intended', $request->fullUrl());
            return redirect()->route('horizon.login');
        }

        return $next($request);
    }

    private function ipAllowed(Request $request): bool
    {
        $raw = (string) config('horizon.ip_allowlist', '');
        if ($raw === '') {
            return true;
        }

        $allowed = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if (empty($allowed)) {
            return true;
        }

        return IpUtils::checkIp((string) $request->ip(), $allowed);
    }
}

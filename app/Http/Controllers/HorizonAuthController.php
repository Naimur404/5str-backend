<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Session login for the Horizon dashboard.
 *
 * Authenticates against the `users` table via Laravel's `web` guard,
 * then enforces the admin role check inline so non-admins never get
 * a session cookie at all. After a successful login the user is
 * bounced to the URL they originally requested (e.g. /horizon/jobs).
 *
 * Rate-limited to 5 attempts per email + IP per minute via the
 * RateLimiter facade.
 */
class HorizonAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect('/'.ltrim((string) config('horizon.path', 'horizon'), '/'));
        }

        return view('horizon.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'horizon-login:' . strtolower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds}s.",
            ]);
        }

        if (! Auth::guard('web')->attempt($credentials, remember: false)) {
            RateLimiter::hit($key, decaySeconds: 60);
            throw ValidationException::withMessages([
                'email' => 'Those credentials don\'t match our records.',
            ]);
        }

        $user = Auth::guard('web')->user();
        if (! $user || ! method_exists($user, 'hasAnyRole')
            || ! $user->hasAnyRole(EnsureAdmin::ADMIN_ROLES)) {
            // Block non-admin users from getting a session cookie.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account doesn\'t have access to this dashboard.',
            ]);
        }

        // Clean session login: rotate the session id, clear rate limit,
        // bounce to the originally-requested URL (or /horizon by default).
        $request->session()->regenerate();
        RateLimiter::clear($key);

        $intended = session()->pull('url.intended');
        $fallback = '/'.ltrim((string) config('horizon.path', 'horizon'), '/');
        return redirect($intended ?: $fallback);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('horizon.login')
            ->with('status', 'You have been signed out.');
    }
}

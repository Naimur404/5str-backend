<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeper for the admin API surface. Allows requests from users who
 * carry any of the admin-tier roles. Returns a JSON 403 (no redirects)
 * so the SPA can react without a flash navigation.
 */
final class EnsureAdmin
{
    public const ADMIN_ROLES = ['super-admin', 'admin', 'moderator'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

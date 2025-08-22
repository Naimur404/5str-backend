<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API routes, always return null to avoid redirects
        if ($request->is('api/*')) {
            return null;
        }
        
        // For web routes, check if they expect JSON
        return $request->expectsJson() ? null : route('login');
    }
}

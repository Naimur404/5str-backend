<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LivewireCsrfBypass
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a Livewire request
        if ($request->is('livewire/*')) {
            // Mark this request to bypass CSRF verification
            $request->attributes->set('livewire-bypass-csrf', true);
        }

        return $next($request);
    }
}

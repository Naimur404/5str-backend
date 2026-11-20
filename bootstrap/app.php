<?php

use App\Http\Middleware\AutoTrackSearches;
use App\Http\Middleware\AutoTrackViews;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\Idempotent;
use App\Http\Middleware\TrackEndpointAnalytics;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Shared API JSON error builder. Kept inline (not autoloaded) so the
 * exception render path doesn't depend on container resolution.
 *
 * Guarded with function_exists() because this file can be required more
 * than once per process (config:cache / route:cache build a fresh app, and
 * so does every test). It must stay above the `return` below: a conditional
 * declaration is not hoisted, so it only exists once execution reaches it.
 */
if (! function_exists('apiJsonError')) {
    function apiJsonError(\Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $payload = [
            'success' => false,
            'message' => $e->getMessage() ?: 'Server error',
        ];

        if ($e instanceof ValidationException) {
            $status = $e->status;
            $payload['message'] = 'Validation failed';
            $payload['errors'] = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $status = 401;
            $payload['message'] = 'Unauthenticated';
        } elseif ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            $status = 404;
            $payload['message'] = 'Resource not found';
        }

        if (config('app.debug') && $status >= 500) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())
                    ->take(10)
                    ->map(static fn ($f) => [
                        'file' => $f['file'] ?? null,
                        'line' => $f['line'] ?? null,
                        'function' => ($f['class'] ?? '').($f['type'] ?? '').($f['function'] ?? ''),
                    ])
                    ->all(),
            ];
        }

        return response()->json($payload, $status);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        then: static function () {
            // Preserve the legacy `/api/admin/*` prefix used by the
            // Next.js admin SPA. Keeping it as a separate file lets the
            // public API and admin API evolve independently.
            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api/admin')
                ->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global stack: trim, validate, CORS, etc.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // The fields that should never be trimmed (passwords contain
        // intentional leading/trailing whitespace).
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // Append per-request analytics middleware to the API group.
        $middleware->api(append: [
            AutoTrackViews::class,
            AutoTrackSearches::class,
            TrackEndpointAnalytics::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'idempotent' => Idempotent::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Operational exceptions never reach Sentry.
        $exceptions->dontReport([
            AuthenticationException::class,
            ValidationException::class,
            ModelNotFoundException::class,
            NotFoundHttpException::class,
        ]);

        $exceptions->reportable(static function (\Throwable $e): void {
            if (app()->bound('sentry')) {
                Integration::captureUnhandledException($e);
            }
        });

        // Force a JSON envelope for any /api/* failure so SPAs and mobile
        // clients never see an HTML stack trace.
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return apiJsonError($e);
        });
    })
    ->create();

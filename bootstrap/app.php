<?php

use App\Http\Middleware\AutoTrackSearches;
use App\Http\Middleware\AutoTrackViews;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\Idempotent;
use App\Http\Middleware\TrackEndpointAnalytics;
use App\Support\ApiExceptionRenderer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            \App\Http\Middleware\HardenJsonResponse::class,
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
        // clients never see an HTML stack trace. The actual rendering
        // lives in App\Support\ApiExceptionRenderer so that re-including
        // this bootstrap file (e.g. during chained artisan optimize calls)
        // never trips PHP's "Cannot redeclare function" guard.
        $exceptions->renderable(static function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiExceptionRenderer::render($e);
        });
    })
    ->create();

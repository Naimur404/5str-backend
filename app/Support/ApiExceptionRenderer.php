<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Single source of truth for the JSON envelope returned by every
 * `/api/*` failure.
 *
 * Wired up from `bootstrap/app.php`'s exception renderer. Kept as a
 * proper autoloaded class (instead of a top-level function in the
 * bootstrap) so that artisan commands which re-include `bootstrap/app.php`
 * during a single PHP-FPM master process (`optimize:clear` followed by
 * `optimize`) do not blow up with a "Cannot redeclare function" error.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
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
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())
                    ->take(10)
                    ->map(static fn (array $f): array => [
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

<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Inputs never flashed to session on validation errors.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Exceptions that should never reach Sentry — expected operational
     * conditions, not bugs.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        AuthenticationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
    ];

    public function register(): void
    {
        $this->reportable(static function (Throwable $e): void {
            if (app()->bound('sentry')) {
                Integration::captureUnhandledException($e);
            }
        });

        // Force a JSON error envelope for any /api/* request so the SPA
        // and mobile clients never get an HTML stack trace.
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $this->renderJson($e);
        });
    }

    private function renderJson(Throwable $e): Response
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

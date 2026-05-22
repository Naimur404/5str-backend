<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-key middleware for unsafe HTTP methods.
 *
 * Clients send a UUID in the `Idempotency-Key` header. The first response
 * is cached and replayed for any matching retry within the TTL. Lookup
 * key is scoped by user id (or anon IP) and request path so a key cannot
 * be reused across endpoints or accounts.
 *
 * Stripe-style semantics: a missing header is allowed (no-op pass), but
 * a present header is enforced strictly.
 */
class Idempotent
{
    /** Cache TTL for replays (seconds). */
    private const TTL = 86400;

    /** Lock TTL while the original request is in flight. */
    private const LOCK_TTL = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '') {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_\-]{8,128}$/', $idempotencyKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Idempotency-Key header.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = $this->cacheKey($request, $idempotencyKey);
        $lockKey = $cacheKey.':lock';

        // Replay path: previous response cached.
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'], $cached['body'])) {
            return $this->buildReplay($cached);
        }

        // Concurrency guard: a parallel retry should not duplicate work.
        $lock = Cache::lock($lockKey, self::LOCK_TTL);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Request with this Idempotency-Key is already being processed.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $response = $next($request);
            $this->store($cacheKey, $response);

            return $response->withHeaders(['Idempotency-Replay' => 'false']);
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
                Log::warning('Idempotent: lock release failed', ['error' => $e->getMessage()]);
            }
        }
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $actor = $request->user()?->getKey() ?? 'ip:'.$request->ip();

        return sprintf(
            'idempotent:%s:%s:%s:%s',
            $actor,
            mb_strtolower($request->method()),
            sha1($request->path()),
            $idempotencyKey,
        );
    }

    private function store(string $cacheKey, Response $response): void
    {
        // Only cache successful responses; failures should be retriable.
        if ($response->getStatusCode() >= 500) {
            return;
        }

        try {
            Cache::put(
                $cacheKey,
                [
                    'status' => $response->getStatusCode(),
                    'headers' => $this->serializableHeaders($response),
                    'body' => $response->getContent(),
                ],
                self::TTL,
            );
        } catch (\Throwable $e) {
            Log::warning('Idempotent: cache store failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array{status:int,headers:array<string,array<int,string>>,body:string}  $cached
     */
    private function buildReplay(array $cached): Response
    {
        $response = response($cached['body'], $cached['status']);
        foreach ($cached['headers'] ?? [] as $name => $values) {
            $response->headers->set($name, $values, true);
        }
        $response->headers->set('Idempotency-Replay', 'true');

        return $response;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function serializableHeaders(Response $response): array
    {
        $allow = ['content-type', 'cache-control', 'etag', 'location'];
        $out = [];
        foreach ($response->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $allow, true)) {
                $out[$name] = $values;
            }
        }

        return $out;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Health probe runner. Each `check*` method must:
 *   - Return ['status' => 'up'|'down', 'latency_ms' => float, 'detail' => string|null]
 *   - Never throw — exceptions become 'down'.
 *   - Run in O(constant time) so the endpoint scales with traffic.
 */
class HealthCheckService
{
    /**
     * Liveness: are we breathing? Returns true unless the process itself
     * is broken. Used by k8s livenessProbe — if false, restart the pod.
     */
    public function liveness(): array
    {
        return [
            'status' => 'up',
            'app' => config('app.name'),
            'env' => app()->environment(),
            'version' => config('app.version', 'dev'),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Readiness: are we able to serve traffic? Probes every external
     * dependency. Used by k8s readinessProbe — if any check is 'down'
     * the pod is removed from the load balancer rotation.
     */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis_default' => $this->checkRedis('default'),
            'redis_cache' => $this->checkRedis('cache'),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $overall = 'up';
        foreach ($checks as $check) {
            if ($check['status'] !== 'up') {
                $overall = 'down';
                break;
            }
        }

        return [
            'status' => $overall,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function checkDatabase(): array
    {
        return $this->probe(static function () {
            DB::connection()->select('SELECT 1');
        });
    }

    private function checkRedis(string $connection): array
    {
        return $this->probe(static function () use ($connection) {
            // Bound the probe so a hung Redis can never lock up the
            // readiness endpoint. 1.5s is plenty for an in-region call.
            $client = Redis::connection($connection)->client();
            if (method_exists($client, 'setOption') && defined('Redis::OPT_READ_TIMEOUT')) {
                $client->setOption(\Redis::OPT_READ_TIMEOUT, 1.5);
            }
            Redis::connection($connection)->ping();
        });
    }

    private function checkCache(): array
    {
        return $this->probe(static function () {
            $key = 'health:probe:'.bin2hex(random_bytes(4));
            Cache::put($key, '1', 5);
            $value = Cache::get($key);
            Cache::forget($key);
            if ($value !== '1') {
                throw new \RuntimeException('cache write/read mismatch');
            }
        });
    }

    /**
     * Probe queue connectivity by listing the failed-jobs key. We avoid
     * pushing a real job because that would create test rows; this just
     * verifies the broker is reachable.
     */
    private function checkQueue(): array
    {
        $connection = config('queue.default');
        if ($connection !== 'redis') {
            return ['status' => 'up', 'latency_ms' => 0.0, 'detail' => "driver={$connection}"];
        }

        return $this->probe(static function () {
            Redis::connection('default')->llen('queues:default');
        });
    }

    private function probe(\Closure $callable): array
    {
        $start = microtime(true);

        try {
            $callable();

            return [
                'status' => 'up',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'detail' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'detail' => $e->getMessage(),
            ];
        }
    }
}

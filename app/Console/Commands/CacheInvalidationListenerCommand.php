<?php

namespace App\Console\Commands;

use App\Services\CacheInvalidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Subscriber for the cross-instance cache-invalidation channel.
 *
 * Run one of these per container alongside Horizon and queue workers:
 *
 *     php artisan cache:invalidation-listen
 *
 * When any container publishes a tag flush, every subscriber receives
 * it and re-runs the flush locally. Process keeps running until
 * SIGTERM.
 *
 * Resilience:
 *   - Uses the dedicated `pubsub` Redis connection (read_timeout = 0)
 *     so the socket is never closed for being idle.
 *   - Wraps `subscribe()` in an outer reconnect loop with capped
 *     exponential backoff. Transient network blips, Coolify proxy
 *     restarts, or Redis maintenance windows no longer cause
 *     supervisord to flap-restart this process every minute.
 */
class CacheInvalidationListenerCommand extends Command
{
    protected $signature = 'cache:invalidation-listen';

    protected $description = 'Listen for distributed cache invalidation events on the Redis pub/sub channel.';

    /** Backoff schedule between reconnection attempts (seconds). */
    private const BACKOFF_SECONDS = [1, 2, 5, 10, 30];

    public function handle(): int
    {
        $channel = CacheInvalidationService::pubsubChannel();
        $host = gethostname() ?: 'unknown';

        $this->info("[{$host}] Subscribing to cache-invalidation channel: {$channel}");

        $attempt = 0;

        // Outer loop = reconnect supervisor. We only return from this
        // command on SIGTERM (which is delivered as a signal handler
        // by Laravel's signal trap, not as an exception), so the loop
        // runs until the process is asked to stop.
        while (true) {
            try {
                $this->subscribeAndProcess($channel, $host);

                // subscribe() returned without throwing — Redis
                // closed the connection cleanly. Reconnect after the
                // shortest backoff.
                $attempt = 0;
                sleep(self::BACKOFF_SECONDS[0]);
            } catch (Throwable $e) {
                $delay = self::BACKOFF_SECONDS[min($attempt, count(self::BACKOFF_SECONDS) - 1)];
                $attempt++;

                Log::warning('CacheInvalidation listener disconnected; will reconnect', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'retry_in_s' => $delay,
                ]);

                sleep($delay);
            }
        }
    }

    /**
     * Open one Redis subscription and dispatch incoming flush messages
     * to the local cache. Returns when subscribe() exits, throws on
     * any underlying connection error.
     */
    private function subscribeAndProcess(string $channel, string $host): void
    {
        Redis::connection('pubsub')->subscribe([$channel], function (string $message) use ($host): void {
            $payload = json_decode($message, true);
            if (! is_array($payload) || ! isset($payload['tags']) || ! is_array($payload['tags'])) {
                return;
            }

            // Skip self-invalidation: avoids a redundant local flush
            // when this same container published the message.
            if (($payload['host'] ?? null) === $host) {
                return;
            }

            $store = Cache::getStore();
            if (! method_exists($store, 'tags')) {
                return;
            }

            try {
                Cache::tags($payload['tags'])->flush();
            } catch (Throwable $e) {
                Log::warning('CacheInvalidation listener: local flush failed', [
                    'tags' => $payload['tags'],
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}

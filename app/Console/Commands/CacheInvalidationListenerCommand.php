<?php

namespace App\Console\Commands;

use App\Services\CacheInvalidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Subscriber for the cross-instance cache-invalidation channel.
 *
 * Run one of these per container alongside Horizon and queue workers:
 *
 *   php artisan cache:invalidation-listen
 *
 * When any container publishes a tag flush, every subscriber receives it
 * and re-runs the flush locally. Process keeps running until SIGTERM.
 */
class CacheInvalidationListenerCommand extends Command
{
    protected $signature = 'cache:invalidation-listen';

    protected $description = 'Listen for distributed cache invalidation events on the Redis pub/sub channel.';

    public function handle(): int
    {
        $channel = CacheInvalidationService::pubsubChannel();
        $host = gethostname() ?: 'unknown';

        $this->info("[{$host}] Subscribed to cache-invalidation channel: {$channel}");

        try {
            Redis::connection('default')->subscribe([$channel], function (string $message) use ($host): void {
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
                } catch (\Throwable $e) {
                    Log::warning('CacheInvalidation listener: local flush failed', [
                        'tags' => $payload['tags'],
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            $this->error('Listener crashed: '.$e->getMessage());
            Log::error('CacheInvalidation listener crashed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

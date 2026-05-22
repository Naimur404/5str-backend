<?php

namespace App\Services;

use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Centralized cache key conventions and tag-based invalidation.
 *
 * Backed by Redis cache tags so flushing a domain ("businesses",
 * "home", "trending"…) is O(1) instead of an O(N) Redis SCAN.
 *
 * Callers that previously did `Cache::remember($key, ttl, fn)` should
 * switch to `CacheInvalidationService::tagged([$tag])->remember(...)`
 * so the entry participates in the tag-flush. Plain `Cache::remember`
 * still works — it just won't be invalidated automatically.
 *
 * Cache key conventions:
 *   - Static lists:    categories:main, categories:featured, categories:popular
 *   - Parameterized:   home:index:{grid}:{radius}, trending:{area}:{period}:{type}
 *   - Grid-snapped:    lat/lng rounded to 2 decimals (~1.1km cells) to prevent key explosion
 */
class CacheInvalidationService
{
    // ─── TTL Constants (seconds) ────────────────────────────────────
    public const TTL_HOME_INDEX        = 180;   // 3 minutes
    public const TTL_CATEGORIES        = 1800;  // 30 minutes
    public const TTL_CATEGORIES_MAIN   = 3600;  // 60 minutes
    public const TTL_FEATURED_SECTIONS = 3600;  // 60 minutes
    public const TTL_STATISTICS        = 900;   // 15 minutes
    public const TTL_TRENDING          = 300;   // 5 minutes
    public const TTL_BUSINESS_FEATURED = 900;   // 15 minutes
    public const TTL_NATIONAL_FILTERS  = 1800;  // 30 minutes
    public const TTL_NATIONAL_LIST     = 900;   // 15 minutes
    public const TTL_SEARCH_POPULAR    = 600;   // 10 minutes
    public const TTL_ATTRACTIONS       = 900;   // 15 minutes
    public const TTL_OFFERS            = 300;   // 5 minutes
    public const TTL_VIEW_ALL          = 600;   // 10 minutes

    // ─── Tag Names ──────────────────────────────────────────────────
    public const TAG_HOME        = 'home';
    public const TAG_CATEGORIES  = 'categories';
    public const TAG_BUSINESSES  = 'businesses';
    public const TAG_TRENDING    = 'trending';
    public const TAG_ATTRACTIONS = 'attractions';
    public const TAG_OFFERS      = 'offers';
    public const TAG_SEARCH      = 'search';

    // ─── Legacy Key Prefix Groups (kept for back-compat with key()) ─
    public const PREFIX_HOME        = 'home:';
    public const PREFIX_CATEGORIES  = 'categories:';
    public const PREFIX_BUSINESSES  = 'businesses:';
    public const PREFIX_TRENDING    = 'trending:';
    public const PREFIX_ATTRACTIONS = 'attractions:';
    public const PREFIX_OFFERS      = 'offers:';
    public const PREFIX_SEARCH      = 'search:';

    /**
     * Snap GPS coordinates to a grid cell for cache key stability.
     * 2 decimal places ≈ 1.1km precision — good balance between
     * cache-hit ratio and location accuracy.
     */
    public static function cacheGrid(?float $lat, ?float $lng): string
    {
        if ($lat === null || $lng === null) {
            return 'no_loc';
        }

        return round($lat, 2).':'.round($lng, 2);
    }

    /**
     * Build a deterministic cache key from prefix + segments.
     */
    public static function key(string $prefix, mixed ...$segments): string
    {
        $parts = array_map(
            static fn ($v) => $v === null ? '_' : (string) $v,
            $segments,
        );

        return $prefix.implode(':', $parts);
    }

    /**
     * Get a tagged cache repository. Falls back to the untagged store on
     * drivers that do not support tags (e.g. file/database) — callers get
     * the same Repository contract either way.
     *
     * @param  array<int, string>  $tags
     */
    public static function tagged(array $tags): Repository|TaggedCache
    {
        $store = Cache::getStore();

        return method_exists($store, 'tags')
            ? Cache::tags($tags)
            : Cache::store();
    }

    // ─── Flush by Domain ────────────────────────────────────────────

    public static function flushHome(): void
    {
        self::flushTag(self::TAG_HOME);
    }

    public static function flushCategories(): void
    {
        self::flushTag(self::TAG_CATEGORIES);
    }

    public static function flushBusinesses(): void
    {
        self::flushTag(self::TAG_BUSINESSES);
    }

    public static function flushTrending(): void
    {
        self::flushTag(self::TAG_TRENDING);
    }

    public static function flushAttractions(): void
    {
        self::flushTag(self::TAG_ATTRACTIONS);
    }

    public static function flushOffers(): void
    {
        self::flushTag(self::TAG_OFFERS);
    }

    public static function flushSearch(): void
    {
        self::flushTag(self::TAG_SEARCH);
    }

    // ─── Composite Flush Methods (for observers) ────────────────────

    public static function onBusinessChange(): void
    {
        self::flushTags([self::TAG_HOME, self::TAG_CATEGORIES, self::TAG_BUSINESSES]);
    }

    public static function onReviewChange(): void
    {
        self::flushTags([self::TAG_HOME, self::TAG_BUSINESSES, self::TAG_CATEGORIES]);
    }

    public static function onOfferChange(): void
    {
        self::flushTags([self::TAG_HOME, self::TAG_OFFERS]);
    }

    public static function onCategoryChange(): void
    {
        self::flushTags([self::TAG_CATEGORIES, self::TAG_HOME]);
    }

    public static function onTrendingRefresh(): void
    {
        self::flushTags([self::TAG_TRENDING, self::TAG_HOME]);
    }

    public static function onAttractionChange(): void
    {
        self::flushTags([self::TAG_ATTRACTIONS, self::TAG_HOME]);
    }

    // ─── Internal Helpers ───────────────────────────────────────────

    /**
     * @param  array<int, string>  $tags
     */
    private static function flushTags(array $tags): void
    {
        try {
            $store = Cache::getStore();
            if (method_exists($store, 'tags')) {
                Cache::tags($tags)->flush();
            }
        } catch (\Throwable $e) {
            Log::error('CacheInvalidation: tagged flush failed', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify peer containers running the same Redis. Each instance has
        // its own subscriber that re-runs `flushTags()` locally; this lets
        // observer-driven invalidation reach every PHP-FPM/queue worker
        // process, not only the one that handled the write.
        self::publishInvalidation($tags);
    }

    /**
     * Pub/Sub fan-out so multi-container deploys converge on the same
     * cache state. No-op when the cache driver is not Redis (single-host
     * deploys don't need it).
     *
     * @param  array<int, string>  $tags
     */
    private static function publishInvalidation(array $tags): void
    {
        if (config('cache.default') !== 'redis') {
            return;
        }

        try {
            Redis::connection('default')->publish(
                self::pubsubChannel(),
                (string) json_encode([
                    'tags' => array_values($tags),
                    'host' => gethostname() ?: 'unknown',
                    'ts' => microtime(true),
                ]),
            );
        } catch (\Throwable $e) {
            // Pub/Sub is best-effort; never block the write request.
            Log::warning('CacheInvalidation: publish failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function pubsubChannel(): string
    {
        return (string) config('cache.invalidation_channel', '5sr_cache_invalidate');
    }

    private static function flushTag(string $tag): void
    {
        self::flushTags([$tag]);
    }
}

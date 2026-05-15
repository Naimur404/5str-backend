<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Centralized cache invalidation service.
 * 
 * All cache keys used across the API read endpoints are registered here 
 * so that model observers can surgically flush the right groups.
 * 
 * Cache key conventions:
 *   - Static lists:    categories:main, categories:featured, categories:popular
 *   - Parameterized:   home:index:{grid}:{radius}, trending:{area}:{period}:{type}
 *   - Grid-snapped:    lat/lng rounded to 2 decimals (~1.1km cells) to prevent key explosion
 */
class CacheInvalidationService
{
    // ─── TTL Constants (seconds) ────────────────────────────────────
    const TTL_HOME_INDEX       = 180;   // 3 minutes
    const TTL_CATEGORIES       = 1800;  // 30 minutes
    const TTL_CATEGORIES_MAIN  = 3600;  // 60 minutes
    const TTL_FEATURED_SECTIONS = 3600; // 60 minutes
    const TTL_STATISTICS       = 900;   // 15 minutes
    const TTL_TRENDING         = 300;   // 5 minutes
    const TTL_BUSINESS_FEATURED = 900;  // 15 minutes
    const TTL_NATIONAL_FILTERS = 1800;  // 30 minutes
    const TTL_NATIONAL_LIST    = 900;   // 15 minutes
    const TTL_SEARCH_POPULAR   = 600;   // 10 minutes
    const TTL_ATTRACTIONS      = 900;   // 15 minutes
    const TTL_OFFERS           = 300;   // 5 minutes
    const TTL_VIEW_ALL         = 600;   // 10 minutes

    // ─── Key Prefix Groups ──────────────────────────────────────────
    const PREFIX_HOME       = 'home:';
    const PREFIX_CATEGORIES = 'categories:';
    const PREFIX_BUSINESSES = 'businesses:';
    const PREFIX_TRENDING   = 'trending:';
    const PREFIX_ATTRACTIONS = 'attractions:';
    const PREFIX_OFFERS     = 'offers:';
    const PREFIX_SEARCH     = 'search:';

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
        return round($lat, 2) . ':' . round($lng, 2);
    }

    /**
     * Build a deterministic cache key from prefix + segments.
     */
    public static function key(string $prefix, ...$segments): string
    {
        $parts = array_map(function ($v) {
            return $v === null ? '_' : (string) $v;
        }, $segments);
        return $prefix . implode(':', $parts);
    }

    // ─── Flush by Domain ────────────────────────────────────────────

    /**
     * Flush all home-related cache keys (called on business/offer/category changes).
     */
    public static function flushHome(): void
    {
        self::flushByPrefix(self::PREFIX_HOME);
    }

    /**
     * Flush all category cache keys.
     */
    public static function flushCategories(): void
    {
        // Flush known static keys
        Cache::forget('categories:index');
        Cache::forget('categories:main');
        Cache::forget('categories:featured');
        Cache::forget('categories:popular');

        // Flush any parameterized category keys
        self::flushByPrefix(self::PREFIX_CATEGORIES);
    }

    /**
     * Flush all business-related cache keys.
     */
    public static function flushBusinesses(): void
    {
        self::flushByPrefix(self::PREFIX_BUSINESSES);
    }

    /**
     * Flush all trending cache keys.
     */
    public static function flushTrending(): void
    {
        self::flushByPrefix(self::PREFIX_TRENDING);
    }

    /**
     * Flush all attraction cache keys.
     */
    public static function flushAttractions(): void
    {
        self::flushByPrefix(self::PREFIX_ATTRACTIONS);
    }

    /**
     * Flush all offer cache keys.
     */
    public static function flushOffers(): void
    {
        self::flushByPrefix(self::PREFIX_OFFERS);
    }

    /**
     * Flush all search-related cache keys.
     */
    public static function flushSearch(): void
    {
        self::flushByPrefix(self::PREFIX_SEARCH);
    }

    // ─── Composite Flush Methods (for observers) ────────────────────

    /**
     * Called when a Business is created, updated, or deleted.
     */
    public static function onBusinessChange(): void
    {
        self::flushHome();
        self::flushCategories();
        self::flushBusinesses();
        
        Log::debug('Cache invalidated: business change');
    }

    /**
     * Called when a Review is created, updated, or deleted.
     */
    public static function onReviewChange(): void
    {
        self::flushHome();
        self::flushBusinesses();
        // Categories popular relies on business counts which may shift with reviews
        Cache::forget('categories:popular');
        
        Log::debug('Cache invalidated: review change');
    }

    /**
     * Called when an Offer is created, updated, or deleted.
     */
    public static function onOfferChange(): void
    {
        self::flushHome();
        self::flushOffers();
        
        Log::debug('Cache invalidated: offer change');
    }

    /**
     * Called when a Category is updated.
     */
    public static function onCategoryChange(): void
    {
        self::flushCategories();
        self::flushHome();
        
        Log::debug('Cache invalidated: category change');
    }

    /**
     * Called when trending data is refreshed by cron.
     */
    public static function onTrendingRefresh(): void
    {
        self::flushTrending();
        self::flushHome();
        
        Log::debug('Cache invalidated: trending refresh');
    }

    /**
     * Called when an Attraction is created, updated, or deleted.
     */
    public static function onAttractionChange(): void
    {
        self::flushAttractions();
        self::flushHome();
        
        Log::debug('Cache invalidated: attraction change');
    }

    // ─── Internal Helpers ───────────────────────────────────────────

    /**
     * Flush all cache keys matching a given prefix using Redis SCAN.
     * Falls back to logging a warning if not using Redis driver.
     */
    private static function flushByPrefix(string $prefix): void
    {
        try {
            $cachePrefix = config('cache.prefix', '');
            $fullPrefix = $cachePrefix . $prefix;

            // Use Redis SCAN for efficient prefix-based deletion
            if (config('cache.default') === 'redis') {
                $connection = Redis::connection('cache');
                $cursor = '0';
                
                do {
                    [$cursor, $keys] = $connection->scan($cursor, [
                        'match' => $fullPrefix . '*',
                        'count' => 100,
                    ]);

                    if (!empty($keys)) {
                        $connection->del(...$keys);
                    }
                } while ($cursor !== '0');
            } else {
                // For non-Redis drivers, we can only forget known keys
                Log::warning("CacheInvalidation: prefix-based flush not supported on driver: " . config('cache.default'));
            }
        } catch (\Exception $e) {
            Log::error("CacheInvalidation: failed to flush prefix '{$prefix}'", [
                'error' => $e->getMessage()
            ]);
        }
    }
}

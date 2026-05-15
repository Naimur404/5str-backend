<?php

namespace App\Console\Commands;

use App\Services\CacheInvalidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FlushApiCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:flush-api 
                            {--group= : Flush specific group (home, categories, businesses, trending, attractions, offers, search)}
                            {--all : Flush all API cache keys}';

    /**
     * The console command description.
     */
    protected $description = 'Flush API endpoint cache keys by group or all at once';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $group = $this->option('group');
        $all = $this->option('all');

        if (!$group && !$all) {
            $this->error('Please specify --group=<name> or --all');
            $this->info('Available groups: home, categories, businesses, trending, attractions, offers, search');
            return self::FAILURE;
        }

        if ($all) {
            $this->info('Flushing ALL API cache keys...');
            CacheInvalidationService::flushHome();
            CacheInvalidationService::flushCategories();
            CacheInvalidationService::flushBusinesses();
            CacheInvalidationService::flushTrending();
            CacheInvalidationService::flushAttractions();
            CacheInvalidationService::flushOffers();
            CacheInvalidationService::flushSearch();
            $this->info('✅ All API caches flushed successfully.');
            return self::SUCCESS;
        }

        match ($group) {
            'home' => CacheInvalidationService::flushHome(),
            'categories' => CacheInvalidationService::flushCategories(),
            'businesses' => CacheInvalidationService::flushBusinesses(),
            'trending' => CacheInvalidationService::flushTrending(),
            'attractions' => CacheInvalidationService::flushAttractions(),
            'offers' => CacheInvalidationService::flushOffers(),
            'search' => CacheInvalidationService::flushSearch(),
            default => null,
        };

        if (!in_array($group, ['home', 'categories', 'businesses', 'trending', 'attractions', 'offers', 'search'])) {
            $this->error("Unknown group: {$group}");
            $this->info('Available groups: home, categories, businesses, trending, attractions, offers, search');
            return self::FAILURE;
        }

        $this->info("✅ '{$group}' cache group flushed successfully.");
        return self::SUCCESS;
    }
}

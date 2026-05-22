<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Attraction;
use App\Models\Business;
use App\Models\Offer;
use App\Models\Review;
use App\Observers\AttractionObserver;
use App\Observers\BusinessObserver;
use App\Observers\OfferObserver;
use App\Observers\ReviewObserver;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Force HTTPS URL generation when served over HTTPS (behind a
        // TLS-terminating proxy). Triggers on production env or an https
        // APP_URL; local dev is untouched.
        if ($this->app->environment('production')
            || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Business::observe(BusinessObserver::class);
        Offer::observe(OfferObserver::class);
        Review::observe(ReviewObserver::class);
        Attraction::observe(AttractionObserver::class);
    }
}

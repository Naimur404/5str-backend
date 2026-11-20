<?php

namespace App\Providers;

use App\Http\SafeResponseFactory;
use App\Models\Attraction;
use App\Models\Business;
use App\Models\Offer;
use App\Models\Review;
use App\Observers\AttractionObserver;
use App\Observers\BusinessObserver;
use App\Observers\OfferObserver;
use App\Observers\ReviewObserver;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Routing\ResponseFactory as BaseResponseFactory;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        // Replace the default response factory with one that bakes
        // UTF-8-tolerant flags into every `response()->json(...)`
        // call. JsonResponse encodes during construction, so a
        // payload with one bad byte would otherwise throw before any
        // middleware could rescue it. With this binding, controllers
        // get the safe flags automatically.
        $this->app->singleton(BaseResponseFactory::class, function ($app) {
            return new SafeResponseFactory(
                $app['Illuminate\Contracts\View\Factory'],
                $app['redirect'],
            );
        });
        $this->app->alias(BaseResponseFactory::class, ResponseFactoryContract::class);
        $this->app->alias(BaseResponseFactory::class, 'response');
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

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Business;
use App\Models\Offer;
use App\Models\Review;
use App\Observers\BusinessObserver;
use App\Observers\OfferObserver;
use App\Observers\ReviewObserver;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Business::observe(BusinessObserver::class);
        Offer::observe(OfferObserver::class);
        Review::observe(ReviewObserver::class);

        // Register all Filament widgets with Livewire
        Livewire::component('app.filament.widgets.platform-stats-overview', \App\Filament\Widgets\PlatformStatsOverview::class);
        Livewire::component('app.filament.widgets.basic-stats-widget', \App\Filament\Widgets\BasicStatsWidget::class);
        Livewire::component('app.filament.widgets.business-growth-chart', \App\Filament\Widgets\BusinessGrowthChart::class);
        Livewire::component('app.filament.widgets.area-usage-chart', \App\Filament\Widgets\AreaUsageChart::class);
        Livewire::component('app.filament.widgets.dashboard-stats', \App\Filament\Widgets\DashboardStats::class);
        Livewire::component('app.filament.widgets.endpoint-analytics-overview', \App\Filament\Widgets\EndpointAnalyticsOverview::class);
        Livewire::component('app.filament.widgets.endpoint-usage-chart', \App\Filament\Widgets\EndpointUsageChart::class);
        Livewire::component('app.filament.widgets.pending-approvals-overview', \App\Filament\Widgets\PendingApprovalsOverview::class);
        Livewire::component('app.filament.widgets.quick-analytics-actions', \App\Filament\Widgets\QuickAnalyticsActions::class);
        Livewire::component('app.filament.widgets.simple-stats-widget', \App\Filament\Widgets\SimpleStatsWidget::class);
    }
}

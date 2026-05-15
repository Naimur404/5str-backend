<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Business;
use App\Models\Offer;
use App\Models\Review;
use App\Observers\BusinessObserver;
use App\Observers\OfferObserver;
use App\Observers\ReviewObserver;
use App\Support\R2Storage;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
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

        FileUpload::macro('r2Storage', function (string $directory) {
            /** @var FileUpload $this */
            return $this
                ->disk(R2Storage::active())
                ->directory($directory)
                ->visibility('public')
                ->fetchFileInformation(false)
                ->saveUploadedFileUsing(function (FileUpload $component, UploadedFile $file) {
                    $directory = $component->getDirectory();
                    $name = $component->getUploadedFileNameForStorage($file);
                    $path = trim(($directory ? rtrim($directory, '/') . '/' : '') . $name, '/');
                    $stream = fopen($file->getRealPath(), 'rb');
                    R2Storage::storage()->put($path, $stream, $component->getVisibility());
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    return R2Storage::storage()->url($path);
                })
                ->getUploadedFileUsing(function (FileUpload $component, string $file, string | array | null $storedFileNames): ?array {
                    $url = R2Storage::urlFromValue($file);
                    if (! $url) {
                        return null;
                    }
                    return [
                        'name' => ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename(parse_url($url, PHP_URL_PATH) ?? $url),
                        'size' => 0,
                        'type' => null,
                        'url' => $url,
                    ];
                })
                ->deleteUploadedFileUsing(function ($file) {
                    R2Storage::delete($file);
                });
        });

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

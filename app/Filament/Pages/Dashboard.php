<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\PlatformStatsOverview;
use App\Filament\Widgets\EndpointUsageChart;
use App\Filament\Widgets\BasicStatsWidget;
use App\Filament\Widgets\BusinessGrowthChart;
use App\Filament\Widgets\AreaUsageChart;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\EndpointAnalyticsOverview;
use App\Filament\Widgets\PendingApprovalsOverview;
use App\Filament\Widgets\QuickAnalyticsActions;
use App\Filament\Widgets\SimpleStatsWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getTitle(): string
    {
        return '5SRT Business Discovery Dashboard';
    }

    public function getHeading(): string
    {
        return '5SRT Business Discovery Platform';
    }

    public function getSubheading(): string
    {
        return 'Welcome to the admin panel. Monitor your API performance and manage platform resources.';
    }

    public function getWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
            BasicStatsWidget::class,
            DashboardStats::class,
            PendingApprovalsOverview::class,
            EndpointAnalyticsOverview::class,
            QuickAnalyticsActions::class,
            EndpointUsageChart::class,
            AreaUsageChart::class,
            BusinessGrowthChart::class,
        ];
    }

    public function getColumns(): int
    {
        return 3;
    }
}

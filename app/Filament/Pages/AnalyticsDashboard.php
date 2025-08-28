<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\EndpointAnalyticsOverview;
use App\Filament\Widgets\EndpointUsageChart;
use App\Filament\Widgets\AreaUsageChart;

class AnalyticsDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    
    protected static ?string $navigationLabel = 'Analytics Dashboard';
    
    protected static ?string $title = 'API Analytics Dashboard';
    
    protected static ?string $navigationGroup = 'Analytics';
    
    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.analytics-dashboard';
    
    protected function getHeaderWidgets(): array
    {
        return [
            EndpointAnalyticsOverview::class,
        ];
    }
    
    protected function getFooterWidgets(): array
    {
        return [
            EndpointUsageChart::class,
            AreaUsageChart::class,
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickAnalyticsActions extends Widget
{
    protected static string $view = 'filament.widgets.quick-analytics-actions';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'actions' => [
                [
                    'title' => 'Analytics Dashboard',
                    'description' => 'Comprehensive analytics with charts and insights',
                    'icon' => 'heroicon-o-chart-pie',
                    'url' => route('filament.admin.pages.analytics-dashboard'),
                    'color' => 'blue',
                ],
                [
                    'title' => 'Endpoint Analytics',
                    'description' => 'Detailed API endpoint usage and performance',
                    'icon' => 'heroicon-o-table-cells',
                    'url' => route('filament.admin.resources.endpoint-analytics.index'),
                    'color' => 'purple',
                ],
                [
                    'title' => 'Search Logs',
                    'description' => 'User search patterns and behavior analysis',
                    'icon' => 'heroicon-o-magnifying-glass',
                    'url' => route('filament.admin.resources.search-logs.index'),
                    'color' => 'green',
                ],
                [
                    'title' => 'Page Views',
                    'description' => 'Business and content interaction tracking',
                    'icon' => 'heroicon-o-eye',
                    'url' => route('filament.admin.resources.views.index'),
                    'color' => 'orange',
                ],
            ]
        ];
    }
}

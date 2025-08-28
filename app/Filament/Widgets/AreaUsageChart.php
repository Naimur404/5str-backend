<?php

namespace App\Filament\Widgets;

use App\Models\EndpointAnalytics;
use Filament\Widgets\ChartWidget;

class AreaUsageChart extends ChartWidget
{
    protected static ?string $heading = 'Top Areas by API Usage (Last 30 Days)';

    protected function getData(): array
    {
        $areaData = EndpointAnalytics::where('created_at', '>=', now()->subMonth())
            ->whereNotNull('user_area')
            ->selectRaw('user_area, COUNT(*) as requests_count')
            ->groupBy('user_area')
            ->orderByDesc('requests_count')
            ->limit(10)
            ->get();

        $labels = $areaData->pluck('user_area')->map(function ($area) {
            // Truncate long area names for better display
            return strlen($area) > 15 ? substr($area, 0, 15) . '...' : $area;
        })->toArray();
        
        $data = $areaData->pluck('requests_count')->toArray();

        // Generate colors
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'
        ];

        return [
            'datasets' => [
                [
                    'label' => 'API Requests',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.label + ": " + context.parsed + " requests";
                        }'
                    ]
                ]
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\EndpointAnalytics;
use Filament\Widgets\ChartWidget;

class EndpointUsageChart extends ChartWidget
{
    protected static ?string $heading = 'Endpoint Usage (Last 7 Days)';

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        
        // Get last 7 days data
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $dayData = EndpointAnalytics::whereDate('created_at', $date->toDateString())
                ->selectRaw('endpoint, COUNT(*) as count')
                ->groupBy('endpoint')
                ->pluck('count', 'endpoint')
                ->toArray();
                
            $data[] = $dayData;
        }

        // Get top 5 endpoints
        $topEndpoints = EndpointAnalytics::where('created_at', '>=', now()->subWeek())
            ->selectRaw('endpoint, COUNT(*) as total_count')
            ->groupBy('endpoint')
            ->orderByDesc('total_count')
            ->limit(5)
            ->pluck('endpoint')
            ->toArray();

        $datasets = [];
        $colors = [
            'rgb(54, 162, 235)',   // Blue
            'rgb(255, 99, 132)',   // Red
            'rgb(75, 192, 192)',   // Green
            'rgb(255, 205, 86)',   // Yellow
            'rgb(153, 102, 255)',  // Purple
        ];

        foreach ($topEndpoints as $index => $endpoint) {
            $endpointData = [];
            foreach ($data as $dayData) {
                $endpointData[] = $dayData[$endpoint] ?? 0;
            }
            
            $datasets[] = [
                'label' => ucfirst(str_replace('_', ' ', $endpoint)),
                'data' => $endpointData,
                'borderColor' => $colors[$index % count($colors)],
                'backgroundColor' => $colors[$index % count($colors)] . '33', // 20% opacity
                'fill' => false,
                'tension' => 0.4,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}

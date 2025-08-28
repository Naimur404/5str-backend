<?php

namespace App\Filament\Widgets;

use App\Models\Business;
use Filament\Widgets\ChartWidget;

class BusinessGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'Business Growth (Last 7 Days)';

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');
            $count = Business::whereDate('created_at', $date->toDateString())->count();
            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Businesses',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

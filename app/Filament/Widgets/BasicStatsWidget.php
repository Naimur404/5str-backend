<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Business;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BasicStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
                
            Stat::make('Total Businesses', Business::count())
                ->description('Business listings')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),
                
            Stat::make('System Status', 'Online')
                ->description('All systems operational')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}

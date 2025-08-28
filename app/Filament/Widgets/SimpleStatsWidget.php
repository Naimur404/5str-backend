<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Business;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SimpleStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $totalBusinesses = Business::count();
        
        return [
            Stat::make('Total Users', $totalUsers)
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
                
            Stat::make('Active Businesses', $totalBusinesses)
                ->description('Live business listings')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),
                
            Stat::make('System Status', 'Online')
                ->description('Platform operational')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}

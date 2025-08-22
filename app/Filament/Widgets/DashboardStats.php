<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Business;
use App\Models\Review;
use App\Models\BusinessOffering;
use App\Models\Banner;
use App\Models\Category;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingReviews = Review::where('status', 'pending')->count();
        $activeBusinesses = Business::where('is_active', true)->count();
        $featuredBusinesses = Business::where('is_featured', true)->count();
        
        return [
            Stat::make('Total Users', User::count())
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
                
            Stat::make('Active Businesses', $activeBusinesses)
                ->description('Live business listings')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info')
                ->chart([5, 3, 8, 6, 12, 8, 15]),
                
            Stat::make('Pending Reviews', $pendingReviews)
                ->description('Awaiting moderation')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingReviews > 0 ? 'warning' : 'success')
                ->chart([2, 4, 3, 5, 2, 3, 1]),
                
            Stat::make('Total Categories', Category::count())
                ->description('Business categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary')
                ->chart([1, 1, 2, 1, 3, 2, 4]),
                
            Stat::make('Active Banners', Banner::where('is_active', true)->count())
                ->description('Live promotional banners')
                ->descriptionIcon('heroicon-m-photo')
                ->color('warning')
                ->chart([2, 1, 3, 2, 4, 3, 5]),
                
            Stat::make('Featured Businesses', $featuredBusinesses)
                ->description('Premium listings')
                ->descriptionIcon('heroicon-m-star')
                ->color('success')
                ->chart([1, 2, 1, 3, 2, 4, 3]),
        ];
    }
}

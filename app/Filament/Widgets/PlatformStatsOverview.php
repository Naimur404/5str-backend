<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Business;
use App\Models\SearchLog;
use App\Models\View;
use App\Models\EndpointAnalytics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class PlatformStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Total Businesses
        try {
            $totalBusinesses = Business::count();
            $businessesThisMonth = Business::whereMonth('created_at', now()->month)->count();
            $businessesLastMonth = Business::whereMonth('created_at', now()->subMonth()->month)->count();
            $businessGrowth = $businessesLastMonth > 0 ? round((($businessesThisMonth - $businessesLastMonth) / $businessesLastMonth) * 100, 1) : 0;
        } catch (\Exception $e) {
            $totalBusinesses = 0;
            $businessGrowth = 0;
        }

        // Total Users
        try {
            $totalUsers = User::count();
            $usersThisMonth = User::whereMonth('created_at', now()->month)->count();
            $usersLastMonth = User::whereMonth('created_at', now()->subMonth()->month)->count();
            $userGrowth = $usersLastMonth > 0 ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 1) : 0;
        } catch (\Exception $e) {
            $totalUsers = 0;
            $userGrowth = 0;
        }

        // Pending Approvals
        try {
            $pendingApprovals = Schema::hasColumn('businesses', 'approval_status') 
                ? Business::where('approval_status', 'pending')->count() 
                : 0;
        } catch (\Exception $e) {
            $pendingApprovals = 0;
        }

        // Search Activity (last 7 days)
        try {
            $searchesWeek = SearchLog::where('created_at', '>=', now()->subWeek())->count();
            $searchesPrevWeek = SearchLog::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->count();
            $searchGrowth = $searchesPrevWeek > 0 ? round((($searchesWeek - $searchesPrevWeek) / $searchesPrevWeek) * 100, 1) : 0;
        } catch (\Exception $e) {
            $searchesWeek = 0;
            $searchGrowth = 0;
        }

        // Page Views (last 7 days)
        try {
            $viewsWeek = View::where('created_at', '>=', now()->subWeek())->count();
            $viewsPrevWeek = View::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->count();
            $viewGrowth = $viewsPrevWeek > 0 ? round((($viewsWeek - $viewsPrevWeek) / $viewsPrevWeek) * 100, 1) : 0;
        } catch (\Exception $e) {
            $viewsWeek = 0;
            $viewGrowth = 0;
        }

        // API Requests today
        try {
            $apiRequestsToday = EndpointAnalytics::whereDate('created_at', today())->count();
            $apiRequestsYesterday = EndpointAnalytics::whereDate('created_at', today()->subDay())->count();
            $apiGrowth = $apiRequestsYesterday > 0 ? round((($apiRequestsToday - $apiRequestsYesterday) / $apiRequestsYesterday) * 100, 1) : 0;
        } catch (\Exception $e) {
            $apiRequestsToday = 0;
            $apiGrowth = 0;
        }

        return [
            Stat::make('Active Businesses', number_format($totalBusinesses))
                ->description($businessGrowth >= 0 ? "+{$businessGrowth}% this month" : "{$businessGrowth}% this month")
                ->descriptionIcon($businessGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($businessGrowth >= 0 ? 'success' : 'danger')
                ->chart($this->getBusinessesChart()),

            Stat::make('Pending Approvals', number_format($pendingApprovals))
                ->description('Businesses awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingApprovals > 0 ? 'warning' : 'success'),

            Stat::make('API Requests Today', number_format($apiRequestsToday))
                ->description($apiGrowth >= 0 ? "+{$apiGrowth}% from yesterday" : "{$apiGrowth}% from yesterday")
                ->descriptionIcon($apiGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($apiGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Weekly Searches', number_format($searchesWeek))
                ->description($searchGrowth >= 0 ? "+{$searchGrowth}% from last week" : "{$searchGrowth}% from last week")
                ->descriptionIcon($searchGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($searchGrowth >= 0 ? 'success' : 'danger'),
        ];
    }

    private function getBusinessesChart(): array
    {
        $data = [];
        try {
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $count = Business::whereDate('created_at', $date)->count();
                $data[] = $count;
            }
        } catch (\Exception $e) {
            $data = [0, 0, 0, 0, 0, 0, 0];
        }
        return $data;
    }

    protected function getColumns(): int
    {
        return 4;
    }
}

<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Models\User;
use App\Models\Business;
use App\Models\SearchLog;
use App\Models\View;
use App\Models\TrendingData;
use Illuminate\Support\Facades\Schema;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.dashboard';

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
        return 'Welcome to the admin panel. Manage businesses, users, and platform settings from here.';
    }

    public function getViewData(): array
    {
        try {
            $totalBusinesses = class_exists(Business::class) ? Business::count() : 0;
        } catch (\Exception $e) {
            $totalBusinesses = 'N/A';
        }

        try {
            $totalUsers = User::count();
        } catch (\Exception $e) {
            $totalUsers = 'N/A';
        }

        try {
            $pendingApprovals = class_exists(Business::class) && Schema::hasColumn('businesses', 'approval_status') 
                ? Business::where('approval_status', 'pending')->count() 
                : 'N/A';
        } catch (\Exception $e) {
            $pendingApprovals = 'N/A';
        }

        try {
            $activeToday = Schema::hasColumn('users', 'last_activity')
                ? User::whereDate('last_activity', today())->count()
                : User::whereDate('updated_at', today())->count();
        } catch (\Exception $e) {
            $activeToday = 'N/A';
        }

        // Analytics data - automatic tracking
        try {
            $searchesToday = class_exists(SearchLog::class) 
                ? SearchLog::whereDate('created_at', today())->count() 
                : 'N/A';
        } catch (\Exception $e) {
            $searchesToday = 'N/A';
        }

        try {
            $viewsToday = class_exists(View::class) 
                ? View::whereDate('created_at', today())->count() 
                : 'N/A';
        } catch (\Exception $e) {
            $viewsToday = 'N/A';
        }

        try {
            $trendingBusinesses = class_exists(TrendingData::class) 
                ? TrendingData::where('period', 'daily')
                    ->whereDate('period_date', today())
                    ->where('data_type', 'business_views')
                    ->count() 
                : 'N/A';
        } catch (\Exception $e) {
            $trendingBusinesses = 'N/A';
        }

        return [
            'totalBusinesses' => $totalBusinesses,
            'totalUsers' => $totalUsers,
            'pendingApprovals' => $pendingApprovals,
            'activeToday' => $activeToday,
            'searchesToday' => $searchesToday,
            'viewsToday' => $viewsToday,
            'trendingBusinesses' => $trendingBusinesses,
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\EndpointAnalytics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EndpointAnalyticsOverview extends BaseWidget
{
    // Removed sort order - only show in Analytics Dashboard, not main dashboard

    protected function getStats(): array
    {
        $todayRequests = EndpointAnalytics::whereDate('created_at', today())->count();
        $yesterdayRequests = EndpointAnalytics::whereDate('created_at', today()->subDay())->count();
        $todayGrowth = $yesterdayRequests > 0 ? round((($todayRequests - $yesterdayRequests) / $yesterdayRequests) * 100, 1) : 0;

        $weekRequests = EndpointAnalytics::where('created_at', '>=', now()->subWeek())->count();
        $previousWeekRequests = EndpointAnalytics::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->count();
        $weekGrowth = $previousWeekRequests > 0 ? round((($weekRequests - $previousWeekRequests) / $previousWeekRequests) * 100, 1) : 0;

        $monthlyUniqueUsers = EndpointAnalytics::where('created_at', '>=', now()->subMonth())
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count();

        $topEndpoint = EndpointAnalytics::where('created_at', '>=', now()->subDay())
            ->selectRaw('endpoint, COUNT(*) as count')
            ->groupBy('endpoint')
            ->orderByDesc('count')
            ->first();

        $topArea = EndpointAnalytics::where('created_at', '>=', now()->subDay())
            ->whereNotNull('user_area')
            ->selectRaw('user_area, COUNT(*) as count')
            ->groupBy('user_area')
            ->orderByDesc('count')
            ->first();

        return [
            Stat::make('Today\'s API Requests', number_format($todayRequests))
                ->description($todayGrowth >= 0 ? "+{$todayGrowth}% from yesterday" : "{$todayGrowth}% from yesterday")
                ->descriptionIcon($todayGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($this->getLastSevenDaysData())
                ->color($todayGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Weekly API Requests', number_format($weekRequests))
                ->description($weekGrowth >= 0 ? "+{$weekGrowth}% from last week" : "{$weekGrowth}% from last week")
                ->descriptionIcon($weekGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weekGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Active Users This Month', number_format($monthlyUniqueUsers))
                ->description('Unique authenticated users')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Top Endpoint Today', $topEndpoint ? ucfirst(str_replace('_', ' ', $topEndpoint->endpoint)) : 'No data')
                ->description($topEndpoint ? "{$topEndpoint->count} requests" : 'No requests today')
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning'),

            Stat::make('Most Active Area', $topArea ? $topArea->user_area : 'No data')
                ->description($topArea ? "{$topArea->count} requests today" : 'No location data')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),
        ];
    }

    private function getLastSevenDaysData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = EndpointAnalytics::whereDate('created_at', $date)->count();
            $data[] = $count;
        }
        return $data;
    }

    protected function getColumns(): int
    {
        return 3;
    }
}

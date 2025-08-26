<?php

namespace App\Filament\Resources\TrendingBusinessResource\Pages;

use App\Filament\Resources\TrendingBusinessResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTrendingBusinesses extends ListRecords
{
    protected static string $resource = TrendingBusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh_trending')
                ->label('Refresh Trending Data')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    // Trigger trending calculation
                    app(\App\Services\AnalyticsService::class)->calculateAllTrending();
                    
                    $this->notify('success', 'Trending data refreshed successfully!');
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Trending')
                ->badge($this->getTabBadgeCount()),

            'daily' => Tab::make('Daily')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('time_period', 'daily'))
                ->badge($this->getTabBadgeCount('daily')),

            'weekly' => Tab::make('Weekly')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('time_period', 'weekly'))
                ->badge($this->getTabBadgeCount('weekly')),

            'monthly' => Tab::make('Monthly')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('time_period', 'monthly'))
                ->badge($this->getTabBadgeCount('monthly')),

            'high_performing' => Tab::make('High Performing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('hybrid_score', '>=', 70))
                ->badge($this->getTabBadgeCount(null, 70)),
        ];
    }

    protected function getTabBadgeCount(?string $period = null, ?float $minScore = null): int
    {
        $query = static::getResource()::getEloquentQuery()
            ->where('date_period', now()->format('Y-m-d'));

        if ($period) {
            $query->where('time_period', $period);
        }

        if ($minScore) {
            $query->where('hybrid_score', '>=', $minScore);
        }

        return $query->count();
    }
}

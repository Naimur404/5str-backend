<?php

namespace App\Filament\Resources\TrendingDataResource\Pages;

use App\Filament\Resources\TrendingDataResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTrendingData extends ViewRecord
{
    protected static string $resource = TrendingDataResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Trending Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('item_type')
                                    ->label('Content Type')
                                    ->icon('heroicon-o-document')
                                    ->badge()
                                    ->color('primary')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'business' => 'Business',
                                        'category' => 'Category',
                                        'search_term' => 'Search Term',
                                        default => ucfirst($state)
                                    }),
                                Infolists\Components\TextEntry::make('item_name')
                                    ->label('Item Name')
                                    ->icon('heroicon-o-star')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('item_display_name')
                                    ->label('Related Item Details')
                                    ->icon('heroicon-o-star')
                                    ->badge()
                                    ->color('success')
                                    ->visible(fn ($record) => $record->item_id !== null),
                            ]),
                    ]),
                
                Infolists\Components\Section::make('Trending Score & Metrics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('trend_score')
                                    ->label('Trending Score')
                                    ->icon('heroicon-o-trophy')
                                    ->badge()
                                    ->color(fn ($state) => match(true) {
                                        $state >= 80 => 'success',
                                        $state >= 60 => 'warning',
                                        $state >= 40 => 'info',
                                        default => 'gray'
                                    })
                                    ->numeric(2)
                                    ->suffix('/100'),
                                Infolists\Components\TextEntry::make('time_period')
                                    ->label('Time Period')
                                    ->icon('heroicon-o-calendar')
                                    ->badge()
                                    ->color('warning')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly',
                                        default => ucfirst($state)
                                    }),
                                Infolists\Components\TextEntry::make('date_period')
                                    ->label('Period Date')
                                    ->icon('heroicon-o-calendar-days')
                                    ->date()
                                    ->badge()
                                    ->color('info'),
                            ]),
                    ]),
                
                Infolists\Components\Section::make('Location & Context')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('location_area')
                                    ->label('Location Area')
                                    ->placeholder('All Areas')
                                    ->icon('heroicon-o-map-pin')
                                    ->badge()
                                    ->color('success'),
                                Infolists\Components\TextEntry::make('item_id')
                                    ->label('Item ID')
                                    ->placeholder('No specific item')
                                    ->icon('heroicon-o-hashtag')
                                    ->visible(fn ($record) => $record->item_id !== null),
                            ]),
                    ]),
                
                Infolists\Components\Section::make('Timestamp Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Calculated At')
                                    ->dateTime()
                                    ->icon('heroicon-o-clock'),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->icon('heroicon-o-arrow-path'),
                            ]),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\SearchLogResource\Pages;

use App\Filament\Resources\SearchLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewSearchLog extends ViewRecord
{
    protected static string $resource = SearchLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Search Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('User')
                                    ->placeholder('Anonymous User')
                                    ->icon('heroicon-o-user'),
                                Infolists\Components\TextEntry::make('search_term')
                                    ->label('Search Term')
                                    ->placeholder('No search term')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->badge()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('category.name')
                                    ->label('Category')
                                    ->placeholder('All Categories')
                                    ->icon('heroicon-o-tag')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('results_count')
                                    ->label('Search Results')
                                    ->icon('heroicon-o-document-text')
                                    ->badge()
                                    ->color(fn ($state) => match(true) {
                                        $state == 0 => 'danger',
                                        $state < 5 => 'warning',
                                        $state < 20 => 'success',
                                        default => 'info'
                                    }),
                            ]),
                    ]),
                
                Infolists\Components\Section::make('Location & Filters')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('user_latitude')
                                    ->label('Latitude')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-map-pin'),
                                Infolists\Components\TextEntry::make('user_longitude')
                                    ->label('Longitude')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-map-pin'),
                            ]),
                        Infolists\Components\KeyValueEntry::make('filters_applied')
                            ->label('Applied Filters')
                            ->placeholder('No filters applied')
                            ->columnSpanFull(),
                    ]),
                
                Infolists\Components\Section::make('Search Outcome')
                    ->schema([
                        Infolists\Components\TextEntry::make('clickedBusiness.business_name')
                            ->label('Clicked Business')
                            ->placeholder('No business clicked')
                            ->icon('heroicon-o-building-office')
                            ->badge()
                            ->color('success')
                            ->url(fn ($record) => $record->clickedBusiness ? 
                                '/admin/businesses/' . $record->clickedBusiness->id : null)
                            ->openUrlInNewTab(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Search Time')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\TrendingBusinessResource\Pages;

use App\Filament\Resources\TrendingBusinessResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTrendingBusiness extends ViewRecord
{
    protected static string $resource = TrendingBusinessResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Business Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\ImageEntry::make('business.logoImage.image_url')
                                    ->label('Logo')
                                    ->circular()
                                    ->size(80),
                                Infolists\Components\TextEntry::make('business.business_name')
                                    ->label('Business Name')
                                    ->size('lg')
                                    ->weight('bold'),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('business.category.name')
                                    ->label('Category')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('business.area')
                                    ->label('Business Area'),
                                Infolists\Components\TextEntry::make('business.overall_rating')
                                    ->label('Rating')
                                    ->formatStateUsing(fn (?float $state): string => $state ? number_format($state, 1) . '/5' : 'No rating')
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Trending Analytics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('hybrid_score')
                                    ->label('Hybrid Score')
                                    ->formatStateUsing(fn (?float $state): string => number_format($state ?? 0, 2))
                                    ->badge()
                                    ->color('success'),
                                Infolists\Components\TextEntry::make('trend_score')
                                    ->label('Trend Score')
                                    ->formatStateUsing(fn (?float $state): string => number_format($state ?? 0, 2))
                                    ->badge()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('view_count')
                                    ->label('Views')
                                    ->formatStateUsing(fn (?int $state): string => number_format($state ?? 0))
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('search_count')
                                    ->label('Searches')
                                    ->formatStateUsing(fn (?int $state): string => number_format($state ?? 0))
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Location & Period')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('location_area')
                                    ->label('Trending Area')
                                    ->badge()
                                    ->color('secondary'),
                                Infolists\Components\TextEntry::make('time_period')
                                    ->label('Time Period')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('date_period')
                                    ->label('Date Period')
                                    ->date(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Performance Metrics')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('business.total_reviews')
                                    ->label('Total Reviews')
                                    ->formatStateUsing(fn (?int $state): string => number_format($state ?? 0)),
                                Infolists\Components\TextEntry::make('business.is_verified')
                                    ->label('Verification Status')
                                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Verified' : 'Not Verified')
                                    ->badge()
                                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger'),
                            ]),
                        Infolists\Components\TextEntry::make('business.description')
                            ->label('Business Description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Contact Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('business.phone')
                                    ->label('Phone')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('business.email')
                                    ->label('Email')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('business.website')
                                    ->label('Website')
                                    ->url(fn (?string $state): ?string => $state)
                                    ->openUrlInNewTab(),
                            ]),
                        Infolists\Components\TextEntry::make('business.full_address')
                            ->label('Full Address')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

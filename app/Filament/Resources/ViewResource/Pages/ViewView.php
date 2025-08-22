<?php

namespace App\Filament\Resources\ViewResource\Pages;

use App\Filament\Resources\ViewResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewView extends ViewRecord
{
    protected static string $resource = ViewResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('View Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('User')
                                    ->placeholder('Anonymous User')
                                    ->icon('heroicon-o-user'),
                                Infolists\Components\TextEntry::make('viewable_type')
                                    ->label('Content Type')
                                    ->icon('heroicon-o-document')
                                    ->badge()
                                    ->color('primary')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'App\\Models\\Business' => 'Business',
                                        'App\\Models\\BusinessOffering' => 'Offering',
                                        'App\\Models\\Category' => 'Category',
                                        default => class_basename($state)
                                    }),
                                Infolists\Components\TextEntry::make('viewable.business_name')
                                    ->label('Business Name')
                                    ->placeholder('Not applicable')
                                    ->icon('heroicon-o-building-office')
                                    ->visible(fn ($record) => $record->viewable_type === 'App\\Models\\Business'),
                                Infolists\Components\TextEntry::make('viewable.offering_name')
                                    ->label('Offering Name')
                                    ->placeholder('Not applicable')
                                    ->icon('heroicon-o-sparkles')
                                    ->visible(fn ($record) => $record->viewable_type === 'App\\Models\\BusinessOffering'),
                                Infolists\Components\TextEntry::make('viewable.category_name')
                                    ->label('Category Name')
                                    ->placeholder('Not applicable')
                                    ->icon('heroicon-o-tag')
                                    ->visible(fn ($record) => $record->viewable_type === 'App\\Models\\Category'),
                            ]),
                    ]),
                
                Infolists\Components\Section::make('Session & Device Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('ip_address')
                                    ->label('IP Address')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-globe-alt')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('session_id')
                                    ->label('Session ID')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-identification')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('user_agent')
                                    ->label('User Agent')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-device-phone-mobile')
                                    ->columnSpanFull()
                                    ->copyable(),
                            ]),
                    ]),
                
                Infolists\Components\Section::make('Timestamp Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Viewed At')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Record Updated')
                                    ->dateTime()
                                    ->icon('heroicon-o-arrow-path'),
                            ]),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\EndpointAnalyticsResource\Pages;

use App\Filament\Resources\EndpointAnalyticsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewEndpointAnalytics extends ViewRecord
{
    protected static string $resource = EndpointAnalyticsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('endpoint')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('Guest User'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Timestamp')
                            ->dateTime()
                            ->since(),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Location Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_area')
                            ->label('Area/Ward')
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('latitude')
                            ->numeric(decimalPlaces: 6),
                        Infolists\Components\TextEntry::make('longitude')
                            ->numeric(decimalPlaces: 6),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Technical Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->lineClamp(3),
                    ])
                    ->columns(1),
                    
                Infolists\Components\Section::make('Additional Data')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('additional_data')
                            ->label('Analytics Data')
                            ->keyLabel('Property')
                            ->valueLabel('Value'),
                    ])
                    ->visible(fn ($record) => !empty($record->additional_data))
                    ->collapsible(),
            ]);
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EndpointAnalyticsResource\Pages;
use App\Filament\Resources\EndpointAnalyticsResource\RelationManagers;
use App\Models\EndpointAnalytics;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;

class EndpointAnalyticsResource extends Resource
{
    protected static ?string $model = EndpointAnalytics::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static ?string $navigationLabel = 'Endpoint Analytics';
    
    protected static ?string $navigationGroup = 'Analytics';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Information')
                    ->schema([
                        Forms\Components\TextInput::make('endpoint')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., home_index, popular_nearby'),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('user_area')
                            ->maxLength(255)
                            ->placeholder('e.g., Dhanmondi Ward 2'),
                    ])
                    ->columns(3),
                    
                Forms\Components\Section::make('Location Data')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001)
                            ->placeholder('23.7465'),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001)
                            ->placeholder('90.3776'),
                        Forms\Components\TextInput::make('ip_address')
                            ->maxLength(255)
                            ->placeholder('192.168.1.1'),
                    ])
                    ->columns(3),
                    
                Forms\Components\Section::make('Technical Details')
                    ->schema([
                        Forms\Components\Textarea::make('user_agent')
                            ->rows(3)
                            ->placeholder('Browser/device information'),
                        Forms\Components\KeyValue::make('additional_data')
                            ->label('Additional Analytics Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('endpoint')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'home_index' => 'success',
                        'popular_nearby' => 'info',
                        'top_rated' => 'warning',
                        'trending' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Guest')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user_area')
                    ->label('Area')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('coordinates')
                    ->label('Location')
                    ->getStateUsing(fn ($record) => $record->latitude && $record->longitude 
                        ? number_format($record->latitude, 4) . ', ' . number_format($record->longitude, 4)
                        : 'N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('endpoint')
                    ->options([
                        'home_index' => 'Home Index',
                        'popular_nearby' => 'Popular Nearby',
                        'top_rated' => 'Top Rated',
                        'trending' => 'Trending',
                        'featured_sections' => 'Featured Sections',
                        'statistics' => 'Statistics',
                        'business_index' => 'Business Index',
                        'business_show' => 'Business Details',
                        'search_businesses' => 'Search Businesses',
                        'search_offerings' => 'Search Offerings',
                        'offering_index' => 'Offering Index',
                        'offering_show' => 'Offering Details',
                    ])
                    ->multiple(),
                    
                SelectFilter::make('user_area')
                    ->options(function () {
                        return EndpointAnalytics::whereNotNull('user_area')
                            ->distinct()
                            ->pluck('user_area', 'user_area')
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),
                    
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date'),
                        DatePicker::make('created_until')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                    
                Filter::make('has_location')
                    ->label('Has Location Data')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('latitude')->whereNotNull('longitude')),
                    
                Filter::make('authenticated_users')
                    ->label('Authenticated Users Only')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('user_id')),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds for real-time analytics
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEndpointAnalytics::route('/'),
            'create' => Pages\CreateEndpointAnalytics::route('/create'),
            'view' => Pages\ViewEndpointAnalytics::route('/{record}'),
            'edit' => Pages\EditEndpointAnalytics::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('created_at', '>=', now()->subDay())->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrendingDataResource\Pages;
use App\Models\TrendingData;
use App\Models\Business;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TrendingDataResource extends Resource
{
    protected static ?string $model = TrendingData::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static ?string $navigationGroup = 'Analytics';
    
    protected static ?string $navigationLabel = 'Trending Data';
    
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user() && Auth::user()->hasAnyRole(['super-admin', 'admin', 'moderator']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('read_only_notice')
                    ->label('')
                    ->content('Trending data is calculated automatically based on user activity and search patterns. This data is read-only for analytics purposes.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'business' => 'Business',
                        'category' => 'Category',
                        'search_term' => 'Search Term',
                        default => ucfirst($state)
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'business' => 'success',
                        'category' => 'info',
                        'search_term' => 'warning',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('location_area')
                    ->label('Area')
                    ->searchable()
                    ->placeholder('All Areas')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trend_score')
                    ->label('Trend Score')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        $state >= 40 => 'info',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('time_period')
                    ->label('Period')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('date_period')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Calculated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('item_type')
                    ->label('Item Type')
                    ->options([
                        'business' => 'Business',
                        'category' => 'Category',
                        'search_term' => 'Search Term',
                    ]),
                Tables\Filters\SelectFilter::make('time_period')
                    ->label('Time Period')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ]),
                Tables\Filters\Filter::make('trend_score')
                    ->form([
                        Forms\Components\TextInput::make('min_score')
                            ->label('Minimum Score')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_score')
                            ->label('Maximum Score')
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min_score'], fn ($query, $score) => $query->where('trend_score', '>=', $score))
                            ->when($data['max_score'], fn ($query, $score) => $query->where('trend_score', '<=', $score));
                    }),
                Tables\Filters\Filter::make('date_period')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_from'], fn ($query, $date) => $query->whereDate('date_period', '>=', $date))
                            ->when($data['date_until'], fn ($query, $date) => $query->whereDate('date_period', '<=', $date));
                    }),
                Tables\Filters\Filter::make('high_performers')
                    ->label('High Performers (Score ≥ 70)')
                    ->query(fn ($query) => $query->where('trend_score', '>=', 70)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Removed Edit and Delete actions - calculated analytics data
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Removed bulk delete - analytics data should be preserved
                ]),
            ])
            ->defaultSort('trend_score', 'desc')
            ->emptyStateHeading('No trending data yet')
            ->emptyStateDescription('Trending data will be calculated automatically based on user activity.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTrendingData::route('/'),
            'view' => Pages\ViewTrendingData::route('/{record}'),
        ];
    }
}

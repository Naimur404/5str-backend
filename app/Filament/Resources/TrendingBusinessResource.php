<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrendingBusinessResource\Pages;
use App\Models\TrendingData;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Builder;

class TrendingBusinessResource extends Resource
{
    protected static ?string $model = TrendingData::class;

    protected static ?string $navigationIcon = 'heroicon-o-trending-up';

    protected static ?string $navigationLabel = 'Trending Businesses';

    protected static ?string $modelLabel = 'Trending Business';

    protected static ?string $pluralModelLabel = 'Trending Businesses';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('item_type', 'business')
            ->with(['business.category', 'business.logoImage'])
            ->orderBy('hybrid_score', 'desc')
            ->orderBy('trend_score', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('item_name')
                    ->label('Business Name')
                    ->disabled(),
                Forms\Components\TextInput::make('trend_score')
                    ->label('Trend Score')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('hybrid_score')
                    ->label('Hybrid Score (Trend + Rating)')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('location_area')
                    ->label('Location Area')
                    ->disabled(),
                Forms\Components\TextInput::make('time_period')
                    ->label('Time Period')
                    ->disabled(),
                Forms\Components\DatePicker::make('date_period')
                    ->label('Date')
                    ->disabled(),
                Forms\Components\TextInput::make('view_count')
                    ->label('View Count')
                    ->numeric()
                    ->disabled(),
                Forms\Components\TextInput::make('search_count')
                    ->label('Search Count')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('business.logoImage.image_url')
                    ->label('Logo')
                    ->circular()
                    ->size(40)
                    ->default('/images/default-business.png'),

                TextColumn::make('business.business_name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (TrendingData $record) => $record->business ? 
                        route('filament.admin.resources.businesses.view', $record->business) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('business.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('location_area')
                    ->label('Area')
                    ->badge()
                    ->color('secondary')
                    ->sortable(),

                TextColumn::make('hybrid_score')
                    ->label('Hybrid Score')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => number_format($state, 2))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        $state >= 40 => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('trend_score')
                    ->label('Trend Score')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => number_format($state, 2))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        $state >= 40 => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('business.overall_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn (?float $state): string => $state ? number_format($state, 1) . '/5' : 'No rating')
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        $state >= 4.5 => 'success',
                        $state >= 4.0 => 'warning',
                        $state >= 3.0 => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('view_count')
                    ->label('Views')
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => number_format($state ?? 0)),

                TextColumn::make('search_count')
                    ->label('Searches')
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => number_format($state ?? 0)),

                TextColumn::make('time_period')
                    ->label('Period')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('date_period')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_area')
                    ->label('Location Area')
                    ->options([
                        'Dhanmondi' => 'Dhanmondi',
                        'Gulshan' => 'Gulshan',
                        'Banani' => 'Banani',
                        'Uttara' => 'Uttara',
                        'Mirpur' => 'Mirpur',
                        'Wari' => 'Wari',
                        'Old Dhaka' => 'Old Dhaka',
                        'Motijheel' => 'Motijheel',
                        'Dhaka Metropolitan' => 'Dhaka Metropolitan',
                        'Chittagong Division' => 'Chittagong Division',
                        'Sylhet Division' => 'Sylhet Division',
                        'Rajshahi Division' => 'Rajshahi Division',
                        'Khulna Division' => 'Khulna Division',
                        'Barisal Division' => 'Barisal Division',
                        'Rangpur Division' => 'Rangpur Division',
                        'Bangladesh' => 'Bangladesh',
                    ]),

                SelectFilter::make('time_period')
                    ->label('Time Period')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ])
                    ->default('daily'),

                SelectFilter::make('business.category_id')
                    ->label('Category')
                    ->relationship('business.category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('hybrid_score', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->emptyStateHeading('No trending businesses found')
            ->emptyStateDescription('Trending data will appear here once businesses start getting views and searches.')
            ->emptyStateIcon('heroicon-o-chart-bar');
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
            'index' => Pages\ListTrendingBusinesses::route('/'),
            'view' => Pages\ViewTrendingBusiness::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('date_period', now()->format('Y-m-d'))
            ->where('trend_score', '>', 0)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}

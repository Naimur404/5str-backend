<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SearchLogResource\Pages;
use App\Models\SearchLog;
use App\Models\User;
use App\Models\Category;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SearchLogResource extends Resource
{
    protected static ?string $model = SearchLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    
    protected static ?string $navigationGroup = 'Analytics';
    
    protected static ?string $navigationLabel = 'Search Logs';
    
    protected static ?int $navigationSort = 1;

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
                    ->content('Search logs are automatically generated when users search for businesses. This data is read-only for analytics purposes.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('Anonymous')
                    ->sortable(),
                Tables\Columns\TextColumn::make('search_term')
                    ->label('Search Term')
                    ->searchable()
                    ->placeholder('No term')
                    ->limit(30),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->placeholder('All Categories'),
                Tables\Columns\TextColumn::make('results_count')
                    ->label('Results')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state == 0 => 'danger',
                        $state < 5 => 'warning',
                        $state < 20 => 'success',
                        default => 'info'
                    }),
                Tables\Columns\TextColumn::make('clickedBusiness.business_name')
                    ->label('Clicked Business')
                    ->searchable()
                    ->placeholder('No Click')
                    ->limit(20),
                Tables\Columns\TextColumn::make('user_latitude')
                    ->label('Lat')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user_longitude')
                    ->label('Lng')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Search Time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->preload(),
                Tables\Filters\Filter::make('has_clicks')
                    ->label('Has Business Clicks')
                    ->query(fn ($query) => $query->whereNotNull('clicked_business_id')),
                Tables\Filters\Filter::make('no_results')
                    ->label('No Results Found')
                    ->query(fn ($query) => $query->where('results_count', 0)),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Removed Edit and Delete actions - read-only analytics data
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Removed bulk delete - analytics data should be preserved
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No search logs yet')
            ->emptyStateDescription('Search logs will appear here as users search for businesses.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSearchLogs::route('/'),
            'view' => Pages\ViewSearchLog::route('/{record}'),
        ];
    }
}

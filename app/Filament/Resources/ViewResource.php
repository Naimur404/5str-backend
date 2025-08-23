<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ViewResource\Pages;
use App\Models\View;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ViewResource extends Resource
{
    protected static ?string $model = View::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye';
    
    protected static ?string $navigationGroup = 'Analytics';
    
    protected static ?string $navigationLabel = 'Views';
    
    protected static ?int $navigationSort = 2;

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
                    ->content('View logs are automatically generated when users view content. This data is read-only for analytics purposes.')
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
                Tables\Columns\TextColumn::make('viewable_type')
                    ->label('Content Type')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'App\\Models\\Business' => 'Business',
                        'App\\Models\\BusinessOffering' => 'Offering',
                        'App\\Models\\Category' => 'Category',
                        'App\\Models\\Offer' => 'Offer',
                        default => 'Unknown'
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'App\\Models\\Business' => 'success',
                        'App\\Models\\BusinessOffering' => 'info',
                        'App\\Models\\Category' => 'warning',
                        'App\\Models\\Offer' => 'danger',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('viewable_id')
                    ->label('Content ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('viewable_name')
                    ->label('Content Name')
                    ->getStateUsing(function ($record) {
                        if ($record->viewable) {
                            return match($record->viewable_type) {
                                'App\\Models\\Business' => $record->viewable->business_name,
                                'App\\Models\\BusinessOffering' => $record->viewable->offering_name,
                                'App\\Models\\Category' => $record->viewable->name,
                                'App\\Models\\Offer' => $record->viewable->title,
                                default => 'Unknown'
                            };
                        }
                        return 'Deleted';
                    })
                    ->limit(30),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Viewed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('viewable_type')
                    ->label('Content Type')
                    ->options([
                        'App\\Models\\Business' => 'Business',
                        'App\\Models\\BusinessOffering' => 'Business Offering',
                        'App\\Models\\Category' => 'Category',
                        'App\\Models\\Offer' => 'Offer',
                    ]),
                Tables\Filters\Filter::make('anonymous_views')
                    ->label('Anonymous Views')
                    ->query(fn ($query) => $query->whereNull('user_id')),
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
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No views yet')
            ->emptyStateDescription('View logs will appear here as users browse content.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageViews::route('/'),
            'view' => Pages\ViewView::route('/{record}'),
        ];
    }
}

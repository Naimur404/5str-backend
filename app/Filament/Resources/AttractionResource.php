<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttractionResource\Pages;
use App\Filament\Resources\AttractionResource\RelationManagers;
use App\Models\Attraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttractionResource extends Resource
{
    protected static ?string $model = Attraction::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    
    protected static ?string $navigationLabel = 'Attractions';
    
    protected static ?string $modelLabel = 'Attraction';
    
    protected static ?string $pluralModelLabel = 'Attractions';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationGroup = 'Tourism Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Attraction Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) => $context === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Auto-generated from name, but you can modify it.'),
                                Forms\Components\Textarea::make('description')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('type')
                                    ->required()
                                    ->options([
                                        'attraction' => 'Attraction',
                                        'activity' => 'Activity',
                                        'spot' => 'Spot',
                                    ])
                                    ->default('attraction'),
                                Forms\Components\TextInput::make('category')
                                    ->placeholder('e.g., Beach, Historical, Nature')
                                    ->datalist([
                                        'Beach',
                                        'Historical',
                                        'Nature',
                                        'Wildlife',
                                        'Archaeological',
                                        'Cultural',
                                        'Adventure',
                                        'Religious',
                                    ]),
                                Forms\Components\TextInput::make('subcategory')
                                    ->placeholder('e.g., Natural Beach, Museum'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Location & Pricing')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->required()
                                            ->numeric()
                                            ->step(0.0001),
                                        Forms\Components\TextInput::make('longitude')
                                            ->required()
                                            ->numeric()
                                            ->step(0.0001),
                                    ]),
                                Forms\Components\TextInput::make('address')
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('city')
                                            ->required(),
                                        Forms\Components\TextInput::make('district'),
                                        Forms\Components\TextInput::make('area'),
                                    ]),
                                Forms\Components\TextInput::make('country')
                                    ->required()
                                    ->default('Bangladesh'),
                                Forms\Components\Section::make('Pricing')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_free')
                                            ->label('Is Free Entry')
                                            ->live(),
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('entry_fee')
                                                    ->numeric()
                                                    ->prefix('৳')
                                                    ->visible(fn (Forms\Get $get) => !$get('is_free')),
                                                Forms\Components\Select::make('currency')
                                                    ->options([
                                                        'BDT' => 'BDT (৳)',
                                                        'USD' => 'USD ($)',
                                                        'EUR' => 'EUR (€)',
                                                    ])
                                                    ->default('BDT')
                                                    ->visible(fn (Forms\Get $get) => !$get('is_free')),
                                            ]),
                                    ]),
                            ]),
                        Forms\Components\Tabs\Tab::make('Details & Facilities')
                            ->schema([
                                Forms\Components\KeyValue::make('opening_hours')
                                    ->label('Opening Hours')
                                    ->keyLabel('Day')
                                    ->valueLabel('Hours (e.g., 09:00-17:00)'),
                                Forms\Components\KeyValue::make('contact_info')
                                    ->label('Contact Information')
                                    ->keyLabel('Type')
                                    ->valueLabel('Value'),
                                Forms\Components\TagsInput::make('facilities')
                                    ->label('Facilities')
                                    ->placeholder('Add facility (press Enter)')
                                    ->suggestions([
                                        'Parking',
                                        'Restrooms',
                                        'Restaurant',
                                        'Gift Shop',
                                        'Guided Tours',
                                        'WiFi',
                                        'ATM',
                                        'First Aid',
                                    ]),
                                Forms\Components\TagsInput::make('best_time_to_visit')
                                    ->label('Best Time to Visit')
                                    ->placeholder('Add months or seasons'),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('estimated_duration_minutes')
                                            ->label('Estimated Duration (minutes)')
                                            ->numeric()
                                            ->suffix('minutes'),
                                        Forms\Components\Select::make('difficulty_level')
                                            ->options([
                                                'easy' => 'Easy',
                                                'moderate' => 'Moderate',
                                                'challenging' => 'Challenging',
                                                'expert' => 'Expert',
                                            ]),
                                    ]),
                                Forms\Components\KeyValue::make('accessibility_info')
                                    ->label('Accessibility Information'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Status & Settings')
                            ->schema([
                                Forms\Components\Section::make('Status')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->default(true),
                                                Forms\Components\Toggle::make('is_featured')
                                                    ->label('Featured'),
                                                Forms\Components\Toggle::make('is_verified')
                                                    ->label('Verified'),
                                            ]),
                                        Forms\Components\Select::make('status')
                                            ->required()
                                            ->options([
                                                'active' => 'Active',
                                                'inactive' => 'Inactive',
                                                'pending' => 'Pending Review',
                                                'rejected' => 'Rejected',
                                            ])
                                            ->default('active'),
                                        Forms\Components\Textarea::make('rejection_reason')
                                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected')
                                            ->columnSpanFull(),
                                    ]),
                                Forms\Components\Section::make('Statistics (Auto-calculated)')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('overall_rating')
                                                    ->numeric()
                                                    ->step(0.1)
                                                    ->disabled()
                                                    ->dehydrated(false),
                                                Forms\Components\TextInput::make('total_reviews')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(false),
                                                Forms\Components\TextInput::make('total_views')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(false),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Image')
                    ->defaultImageUrl('/images/default-attraction.jpg')
                    ->size(60)
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Beach' => 'info',
                        'Historical' => 'warning', 
                        'Nature' => 'success',
                        'Wildlife' => 'success',
                        'Archaeological' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_free')
                    ->boolean()
                    ->label('Free')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-banknotes')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('entry_fee')
                    ->money('BDT')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn ($record) => !$record?->is_free),
                Tables\Columns\TextColumn::make('overall_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '★' : 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_reviews')
                    ->label('Reviews')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'inactive' => 'gray',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_views')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Beach' => 'Beach',
                        'Historical' => 'Historical',
                        'Nature' => 'Nature',
                        'Wildlife' => 'Wildlife',
                        'Archaeological' => 'Archaeological',
                    ]),
                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Free Entry'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'inactive' => 'Inactive',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_featured')
                        ->label('Mark as Featured')
                        ->icon('heroicon-o-star')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_featured' => true])))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('mark_verified')
                        ->label('Mark as Verified')
                        ->icon('heroicon-o-check-badge')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_verified' => true])))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\GalleriesRelationManager::class,
            RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttractions::route('/'),
            'create' => Pages\CreateAttraction::route('/create'),
            'edit' => Pages\EditAttraction::route('/{record}/edit'),
        ];
    }
}

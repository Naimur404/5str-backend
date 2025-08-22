<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessOfferingResource\Pages;
use App\Filament\Resources\BusinessOfferingResource\RelationManagers;
use App\Models\BusinessOffering;
use App\Models\Business;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BusinessOfferingResource extends Resource
{
    protected static ?string $model = BusinessOffering::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    
    protected static ?string $navigationGroup = 'Business Management';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // If user is business-owner, only show offerings from their own businesses
        if (Auth::user() && Auth::user()->hasRole('business-owner')) {
            $query->whereHas('business', function (Builder $query) {
                $query->where('owner_user_id', Auth::id());
            });
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->options(function () {
                                if (Auth::user() && Auth::user()->hasRole('business-owner')) {
                                    return Business::where('owner_user_id', Auth::id())->pluck('business_name', 'id');
                                }
                                return Business::all()->pluck('business_name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->rows(3),
                        Forms\Components\Select::make('offering_type')
                            ->options([
                                'product' => 'Product',
                                'service' => 'Service',
                                'menu' => 'Menu Item',
                            ])
                            ->required(),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(Category::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Price (BDT)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Enter base price'),
                        Forms\Components\TextInput::make('price_max')
                            ->label('Maximum Price (BDT)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('For range pricing (optional)'),
                        Forms\Components\TextInput::make('currency')
                            ->default('BDT')
                            ->maxLength(3),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Media & Status')
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->directory('offerings/images')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120), // 5MB
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Lower numbers appear first'),
                        Forms\Components\Toggle::make('is_available')
                            ->default(true),
                        Forms\Components\Toggle::make('is_popular')
                            ->default(false),
                        Forms\Components\Toggle::make('is_featured')
                            ->default(false),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('business.business_name')
                    ->label('Business')
                    ->searchable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('offering_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'product' => 'success',
                        'service' => 'warning',
                        'menu' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Rating')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('total_reviews')
                    ->label('Reviews')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_popular')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business')
                    ->relationship('business', 'business_name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('offering_type')
                    ->options([
                        'product' => 'Product',
                        'service' => 'Service',
                        'menu' => 'Menu Item',
                    ]),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Available'),
                Tables\Filters\TernaryFilter::make('is_popular')
                    ->label('Popular'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBusinessOfferings::route('/'),
        ];
    }
}

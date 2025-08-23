<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\Offer;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    
    protected static ?string $navigationGroup = 'Business Management';
    
    protected static ?string $navigationLabel = 'Offers & Promotions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Offer Information')
                    ->schema([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->options(Business::all()->pluck('business_name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                        Forms\Components\TextInput::make('offer_code')
                            ->label('Promo Code')
                            ->placeholder('Optional promo code')
                            ->maxLength(255),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Offer Details')
                    ->schema([
                        Forms\Components\Select::make('offer_type')
                            ->label('Offer Type')
                            ->options([
                                'percentage' => 'Percentage Discount',
                                'fixed_amount' => 'Fixed Amount Off',
                                'bogo' => 'Buy One Get One',
                                'combo' => 'Combo Deal',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('discount_percentage')
                            ->label('Discount Percentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Forms\Get $get) => $get('offer_type') === 'percentage'),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount (BDT)')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Forms\Get $get) => $get('offer_type') === 'fixed_amount'),
                        Forms\Components\TextInput::make('minimum_spend')
                            ->label('Minimum Spend (BDT)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Minimum amount to qualify for offer'),
                        Forms\Components\TextInput::make('usage_limit')
                            ->label('Usage Limit')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Maximum number of times this offer can be used'),
                        Forms\Components\TextInput::make('current_usage')
                            ->label('Current Usage')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Validity & Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false),
                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Valid Until')
                            ->required()
                            ->native(false),
                        Forms\Components\CheckboxList::make('applicable_days')
                            ->label('Applicable Days')
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday', 
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                            ])
                            ->columns(4)
                            ->helperText('Leave empty to apply all days'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Media & Status')
                    ->schema([
                        Forms\Components\FileUpload::make('banner_image')
                            ->label('Banner Image')
                            ->image()
                            ->directory('offers/banners')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120), // 5MB
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Offer')
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business.business_name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Discount %')
                    ->numeric()
                    ->suffix('%')
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount Amount')
                    ->money('BDT')
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('offer_code')
                    ->label('Promo Code')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No Code'),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_to')
                    ->label('Valid Until')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_usage')
                    ->label('Usage')
                    ->formatStateUsing(fn ($record) => $record->current_usage . '/' . ($record->usage_limit ?? '∞'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business')
                    ->relationship('business', 'business_name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('offer_type')
                    ->options([
                        'percentage' => 'Percentage Discount',
                        'fixed_amount' => 'Fixed Amount Off',
                        'bogo' => 'Buy One Get One',
                        'combo' => 'Combo Deal',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\Filter::make('valid_offers')
                    ->label('Currently Valid')
                    ->query(fn ($query) => $query->where('valid_from', '<=', now())->where('valid_to', '>=', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Offer $record) {
                        $newOffer = $record->replicate();
                        $newOffer->title = $record->title . ' (Copy)';
                        $newOffer->current_usage = 0;
                        $newOffer->save();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOffers::route('/'),
        ];
    }
}

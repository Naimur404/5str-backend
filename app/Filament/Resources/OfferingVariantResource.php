<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferingVariantResource\Pages;
use App\Models\OfferingVariant;
use App\Models\BusinessOffering;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfferingVariantResource extends Resource
{
    protected static ?string $model = OfferingVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static ?string $navigationGroup = 'Business Management';
    
    protected static ?string $navigationLabel = 'Offering Variants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Variant Details')
                    ->schema([
                        Forms\Components\Select::make('offering_id')
                            ->label('Business Offering')
                            ->options(function () {
                                return BusinessOffering::with('business')
                                    ->get()
                                    ->mapWithKeys(function ($offering) {
                                        return [$offering->id => $offering->business->business_name . ' - ' . $offering->name];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('variant_name')
                            ->label('Variant Type')
                            ->placeholder('e.g., Size, Color, Type')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('variant_value')
                            ->label('Variant Value')
                            ->placeholder('e.g., Large, Red, Premium')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('price_adjustment')
                            ->label('Price Adjustment')
                            ->numeric()
                            ->placeholder('Enter positive for additional cost, negative for discount')
                            ->helperText('Leave empty for no price change'),
                        Forms\Components\Toggle::make('is_available')
                            ->label('Available')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('offering.business.business_name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offering.name')
                    ->label('Offering')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('variant_name')
                    ->label('Variant Type')
                    ->searchable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('variant_value')
                    ->label('Variant Value')
                    ->searchable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('price_adjustment')
                    ->label('Price Adjustment')
                    ->money('BDT')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray')),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('offering')
                    ->relationship('offering', 'name')
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Available'),
                Tables\Filters\Filter::make('variant_name')
                    ->form([
                        Forms\Components\TextInput::make('variant_name')
                            ->placeholder('Search by variant type'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['variant_name'],
                            fn ($query, $name) => $query->where('variant_name', 'like', "%{$name}%")
                        );
                    }),
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
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOfferingVariants::route('/'),
        ];
    }
}

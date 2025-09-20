<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserAttractionInteractionResource\Pages;
use App\Filament\Resources\UserAttractionInteractionResource\RelationManagers;
use App\Models\UserAttractionInteraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserAttractionInteractionResource extends Resource
{
    protected static ?string $model = UserAttractionInteraction::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';
    
    protected static ?string $navigationLabel = 'User Interactions';
    
    protected static ?string $modelLabel = 'User Interaction';
    
    protected static ?string $pluralModelLabel = 'User Interactions';
    
    protected static ?int $navigationSort = 4;
    
    protected static ?string $navigationGroup = 'Tourism Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('attraction_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('interaction_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('interaction_data'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('visit_info'),
                Forms\Components\Toggle::make('is_public')
                    ->required(),
                Forms\Components\TextInput::make('user_rating')
                    ->numeric(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\DateTimePicker::make('interaction_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attraction_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('interaction_type')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->boolean(),
                Tables\Columns\TextColumn::make('user_rating')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('interaction_date')
                    ->dateTime()
                    ->sortable(),
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
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUserAttractionInteractions::route('/'),
            'create' => Pages\CreateUserAttractionInteraction::route('/create'),
            'edit' => Pages\EditUserAttractionInteraction::route('/{record}/edit'),
        ];
    }
}

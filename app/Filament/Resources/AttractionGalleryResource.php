<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttractionGalleryResource\Pages;
use App\Filament\Resources\AttractionGalleryResource\RelationManagers;
use App\Models\AttractionGallery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttractionGalleryResource extends Resource
{
    protected static ?string $model = AttractionGallery::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    
    protected static ?string $navigationLabel = 'Gallery';
    
    protected static ?string $modelLabel = 'Gallery Image';
    
    protected static ?string $pluralModelLabel = 'Gallery Images';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationGroup = 'Tourism Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('attraction_id')
                    ->required()
                    ->numeric(),
                Forms\Components\FileUpload::make('image_url')
                    ->image()
                    ->required(),
                Forms\Components\FileUpload::make('image_path')
                    ->image(),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('alt_text')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_cover')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\FileUpload::make('image_type')
                    ->image()
                    ->required(),
                Forms\Components\TextInput::make('meta_data'),
                Forms\Components\TextInput::make('uploaded_by')
                    ->numeric(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attraction_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_url'),
                Tables\Columns\ImageColumn::make('image_path'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('alt_text')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_cover')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image_type'),
                Tables\Columns\TextColumn::make('uploaded_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
            'index' => Pages\ListAttractionGalleries::route('/'),
            'create' => Pages\CreateAttractionGallery::route('/create'),
            'edit' => Pages\EditAttractionGallery::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\BusinessResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BusinessImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Business Images';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('image_type')
                    ->label('Image Type')
                    ->options([
                        'logo' => 'Logo',
                        'cover' => 'Cover Image',
                        'gallery' => 'Gallery Image',
                    ])
                    ->required(),
                Forms\Components\FileUpload::make('image_url')
                    ->label('Image')
                    ->image()
                    ->required()
                    ->directory('business/images')
                    ->disk('public')
                    ->visibility('public')
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240), // 10MB
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Image')
                    ->default(false)
                    ->helperText('Mark as primary image for this type'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image_type')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->size(80),
                Tables\Columns\TextColumn::make('image_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'logo' => 'success',
                        'cover' => 'warning',
                        'gallery' => 'info',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('image_type')
                    ->options([
                        'logo' => 'Logo',
                        'cover' => 'Cover Image',
                        'gallery' => 'Gallery Image',
                    ]),
                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary Images'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}

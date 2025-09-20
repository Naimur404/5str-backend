<?php

namespace App\Filament\Resources\AttractionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GalleriesRelationManager extends RelationManager
{
    protected static string $relationship = 'galleries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('attractions/gallery')
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(2),
                Forms\Components\TextInput::make('image_url')
                    ->label('External Image URL')
                    ->url()
                    ->helperText('Use this if you want to link to an external image instead of uploading'),
                Forms\Components\Toggle::make('is_cover')
                    ->label('Set as Cover Image')
                    ->helperText('Only one image can be the cover image'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Display Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\ImageColumn::make('full_url')
                    ->label('Image')
                    ->size(80),
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_cover')
                    ->boolean()
                    ->label('Cover')
                    ->trueColor('success'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_cover')
                    ->label('Cover Image'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('setCover')
                    ->label('Set as Cover')
                    ->icon('heroicon-o-star')
                    ->action(function ($record) {
                        // Remove cover from other images
                        $record->attraction->galleries()->update(['is_cover' => false]);
                        // Set this as cover
                        $record->update(['is_cover' => true]);
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => !$record->is_cover),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}

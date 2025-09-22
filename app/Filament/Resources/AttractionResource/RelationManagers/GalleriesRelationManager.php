<?php

namespace App\Filament\Resources\AttractionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class GalleriesRelationManager extends RelationManager
{
    protected static string $relationship = 'galleries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('image_path')
                    ->label('Upload Image')
                    ->image()
                    ->disk('public')
                    ->directory('attractions/gallery')
                    ->imageEditor()
                    ->helperText('Upload an image file or use the external URL field below'),
                Forms\Components\TextInput::make('image_url')
                    ->label('External Image URL')
                    ->url()
                    ->helperText('Alternatively, provide an external image URL'),
                Forms\Components\TextInput::make('title')
                    ->label('Image Title')
                    ->maxLength(255)
                    ->placeholder('Enter a descriptive title'),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->placeholder('Describe what this image shows'),
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->processImageData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->processImageData($data);
    }

    private function processImageData(array $data): array
    {
        // If image_path is provided but image_url is empty, use the image_path as image_url
        if (!empty($data['image_path']) && empty($data['image_url'])) {
            // Store the path without /storage prefix - the accessor will handle URL generation
            $data['image_url'] = $data['image_path'];
        }
        
        // If neither image_path nor image_url is provided, we need at least one
        if (empty($data['image_path']) && empty($data['image_url'])) {
            throw new \Exception('Either upload an image file or provide an external image URL.');
        }
        
        return $data;
    }
}

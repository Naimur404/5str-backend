<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttractionGalleryResource\Pages;
use App\Filament\Resources\AttractionGalleryResource\RelationManagers;
use App\Models\AttractionGallery;
use App\Models\Attraction;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

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
                Forms\Components\Select::make('attraction_id')
                    ->label('Attraction')
                    ->relationship('attraction', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Select the attraction this image belongs to'),
                
                Forms\Components\Section::make('Image Upload')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Upload Image')
                            ->image()
                            ->disk('public')
                            ->directory('attractions/gallery')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3', 
                                '1:1',
                            ])
                            ->helperText('Upload an image file. This will be stored locally.'),
                        
                        Forms\Components\TextInput::make('image_url')
                            ->label('External Image URL')
                            ->url()
                            ->helperText('Alternatively, provide an external image URL. This will be auto-filled if you upload a file above.'),
                        
                        Forms\Components\Placeholder::make('image_note')
                            ->label('')
                            ->content('Note: You must either upload an image file OR provide an external image URL.')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Forms\Components\Section::make('Image Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Image Title')
                            ->maxLength(255)
                            ->placeholder('Enter a descriptive title for this image'),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->placeholder('Describe what this image shows...'),
                        
                        Forms\Components\TextInput::make('alt_text')
                            ->label('Alt Text')
                            ->maxLength(255)
                            ->helperText('Alternative text for accessibility'),
                    ])->columns(1),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_cover')
                            ->label('Set as Cover Image')
                            ->helperText('Only one image can be the cover image for an attraction')
                            ->default(false),
                        
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first in the gallery'),
                        
                        Forms\Components\Select::make('image_type')
                            ->label('Image Type')
                            ->options([
                                'gallery' => 'Gallery Image',
                                'cover' => 'Cover Image',
                                'thumbnail' => 'Thumbnail',
                                'featured' => 'Featured Image',
                            ])
                            ->default('gallery')
                            ->required(),
                        
                        Forms\Components\Select::make('uploaded_by')
                            ->label('Uploaded By')
                            ->relationship('uploader', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Select the user who uploaded this image'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive images will not be displayed'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attraction.name')
                    ->label('Attraction')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->attraction?->city),
                
                Tables\Columns\ImageColumn::make('full_image_url')
                    ->label('Image')
                    ->size(80)
                    ->circular(false),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->description(fn ($record) => $record->description ? \Illuminate\Support\Str::limit($record->description, 50) : null),
                
                Tables\Columns\TextColumn::make('image_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cover' => 'success',
                        'featured' => 'warning',
                        'gallery' => 'info',
                        'thumbnail' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\IconColumn::make('is_cover')
                    ->label('Cover')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attraction')
                    ->relationship('attraction', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('image_type')
                    ->options([
                        'gallery' => 'Gallery Image',
                        'cover' => 'Cover Image',
                        'thumbnail' => 'Thumbnail',
                        'featured' => 'Featured Image',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_cover')
                    ->label('Cover Image'),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('setCover')
                    ->label('Set as Cover')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->action(function ($record) {
                        // Remove cover from other images of the same attraction
                        $record->attraction->galleries()->update(['is_cover' => false]);
                        // Set this as cover
                        $record->update(['is_cover' => true]);
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record && !$record->is_cover),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_active' => true])))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_active' => false])))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
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

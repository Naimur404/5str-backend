<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessImageResource\Pages;
use App\Models\BusinessImage;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BusinessImageResource extends Resource
{
    protected static ?string $model = BusinessImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    
    protected static ?string $navigationGroup = 'Business Management';
    
    protected static ?string $navigationLabel = 'Business Images';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Image Details')
                    ->schema([
                        Forms\Components\Select::make('business_id')
                            ->label('Business')
                            ->options(Business::all()->pluck('business_name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
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
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->size(80),
                Tables\Columns\TextColumn::make('business.business_name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business')
                    ->relationship('business', 'business_name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('image_type')
                    ->options([
                        'logo' => 'Logo',
                        'cover' => 'Cover Image',
                        'gallery' => 'Gallery Image',
                    ]),
                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary Images'),
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
            ->defaultSort('id', 'desc')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBusinessImages::route('/'),
        ];
    }
}

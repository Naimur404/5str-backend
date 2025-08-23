<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    
    protected static ?string $navigationGroup = 'Content Management';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Banner Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3),
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Banner Image')
                            ->image()
                            ->required()
                            ->directory('banners/images')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240), // 10MB
                        Forms\Components\TextInput::make('link_url')
                            ->label('Link URL')
                            ->url()
                            ->maxLength(255),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\Select::make('banner_type')
                            ->options([
                                'hero' => 'Hero Banner',
                                'promotional' => 'Promotional',
                                'category' => 'Category Banner',
                                'business' => 'Business Feature',
                            ])
                            ->required(),
                        Forms\Components\Select::make('position')
                            ->options([
                                'top' => 'Top',
                                'middle' => 'Middle',
                                'bottom' => 'Bottom',
                                'sidebar' => 'Sidebar',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Forms\Components\DateTimePicker::make('start_date')
                            ->required(),
                        Forms\Components\DateTimePicker::make('end_date'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->size(80),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('banner_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hero' => 'primary',
                        'promotional' => 'success',
                        'category' => 'warning',
                        'business' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('position')
                    ->badge(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('banner_type')
                    ->options([
                        'hero' => 'Hero Banner',
                        'promotional' => 'Promotional',
                        'category' => 'Category Banner',
                        'business' => 'Business Feature',
                    ]),
                Tables\Filters\SelectFilter::make('position')
                    ->options([
                        'top' => 'Top',
                        'middle' => 'Middle',
                        'bottom' => 'Bottom',
                        'sidebar' => 'Sidebar',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBanners::route('/'),
        ];
    }
}

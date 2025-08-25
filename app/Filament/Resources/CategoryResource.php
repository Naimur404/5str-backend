<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    
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
                Forms\Components\Section::make('Category Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $context, $state, callable $set) => 
                                $context === 'create' ? $set('slug', Str::slug($state)) : null
                            ),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(Category::whereNull('parent_id')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Select parent (leave empty for main category)'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Visual & Display')
                    ->schema([
                        Forms\Components\FileUpload::make('icon_image')
                            ->label('Category Icon')
                            ->image()
                            ->disk('public')
                            ->directory('category-icons')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('banner_image')
                            ->label('Category Banner Image')
                            ->image()
                            ->disk('public')
                            ->directory('category-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                        Forms\Components\ColorPicker::make('color_code')
                            ->label('Theme Color'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_featured')
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon_image')
                    ->label('Icon')
                    ->disk('public')
                    ->size(40)
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->badge()
                    ->color('info')
                    ->placeholder('Main Category'),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ColorColumn::make('color_code')
                    ->label('Color'),
                Tables\Columns\TextColumn::make('businesses_count')
                    ->label('Businesses')
                    ->counts('businesses')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options(Category::whereNull('parent_id')->pluck('name', 'id'))
                    ->placeholder('All Categories'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('add_subcategory')
                    ->label('Add Subcategory')
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000),
                        Forms\Components\FileUpload::make('icon_image')
                            ->image()
                            ->directory('categories'),
                        Forms\Components\ColorPicker::make('color_code'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_featured')
                            ->default(false),
                    ])
                    ->action(function (array $data, $record) {
                        $data['parent_id'] = $record->id;
                        $data['level'] = $record->level + 1;
                        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
                        Category::create($data);
                    })
                    ->visible(fn ($record) => $record->parent_id === null),
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
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}

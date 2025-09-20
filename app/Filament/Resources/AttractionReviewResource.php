<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttractionReviewResource\Pages;
use App\Filament\Resources\AttractionReviewResource\RelationManagers;
use App\Models\AttractionReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttractionReviewResource extends Resource
{
    protected static ?string $model = AttractionReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    
    protected static ?string $navigationLabel = 'Reviews';
    
    protected static ?string $modelLabel = 'Review';
    
    protected static ?string $pluralModelLabel = 'Reviews';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationGroup = 'Tourism Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('attraction_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('rating')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255),
                Forms\Components\Textarea::make('comment')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('visit_info'),
                Forms\Components\TextInput::make('experience_tags'),
                Forms\Components\Toggle::make('is_verified')
                    ->required(),
                Forms\Components\Toggle::make('is_featured')
                    ->required(),
                Forms\Components\Toggle::make('is_anonymous')
                    ->required(),
                Forms\Components\TextInput::make('helpful_votes')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('total_votes')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('active'),
                Forms\Components\Textarea::make('admin_notes')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('visit_date'),
                Forms\Components\TextInput::make('meta_data'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attraction_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_anonymous')
                    ->boolean(),
                Tables\Columns\TextColumn::make('helpful_votes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_votes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('visit_date')
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
            'index' => Pages\ListAttractionReviews::route('/'),
            'create' => Pages\CreateAttractionReview::route('/create'),
            'edit' => Pages\EditAttractionReview::route('/{record}/edit'),
        ];
    }
}

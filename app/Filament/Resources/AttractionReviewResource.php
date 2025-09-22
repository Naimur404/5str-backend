<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttractionReviewResource\Pages;
use App\Filament\Resources\AttractionReviewResource\RelationManagers;
use App\Models\AttractionReview;
use App\Models\Attraction;
use App\Models\User;
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
                Forms\Components\Section::make('Review Information')
                    ->schema([
                        Forms\Components\Select::make('attraction_id')
                            ->label('Attraction')
                            ->relationship('attraction', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the attraction being reviewed'),

                        Forms\Components\Select::make('user_id')
                            ->label('Reviewer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the user who wrote this review'),

                        Forms\Components\Select::make('rating')
                            ->label('Rating')
                            ->options([
                                '1' => '★ (1 - Poor)',
                                '2' => '★★ (2 - Fair)',
                                '3' => '★★★ (3 - Good)',
                                '4' => '★★★★ (4 - Very Good)',
                                '5' => '★★★★★ (5 - Excellent)',
                            ])
                            ->required()
                            ->helperText('Select a rating from 1 to 5 stars'),
                    ])->columns(3),

                Forms\Components\Section::make('Review Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Review Title')
                            ->maxLength(255)
                            ->placeholder('Enter a catchy title for this review'),

                        Forms\Components\Textarea::make('comment')
                            ->label('Review Comment')
                            ->required()
                            ->rows(4)
                            ->placeholder('Write your detailed review here...')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Visit Details')
                    ->schema([
                        Forms\Components\DateTimePicker::make('visit_date')
                            ->label('Visit Date')
                            ->helperText('When did you visit this attraction?'),

                        Forms\Components\TagsInput::make('experience_tags')
                            ->label('Experience Tags')
                            ->placeholder('Add tags like "family-friendly", "romantic", "adventure", etc.')
                            ->suggestions([
                                'family-friendly',
                                'romantic',
                                'adventure',
                                'peaceful',
                                'crowded',
                                'scenic',
                                'educational',
                                'fun',
                                'relaxing',
                                'exciting',
                            ])
                            ->helperText('Tags that describe the experience'),

                        Forms\Components\KeyValue::make('visit_info')
                            ->label('Visit Information')
                            ->keyLabel('Detail')
                            ->valueLabel('Information')
                            ->helperText('Additional information about the visit (e.g., weather: sunny, crowd: moderate)')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Review Settings')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_verified')
                                    ->label('Verified Review')
                                    ->helperText('Mark if this review is verified by admin'),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured Review')
                                    ->helperText('Mark if this review should be featured'),

                                Forms\Components\Toggle::make('is_anonymous')
                                    ->label('Anonymous Review')
                                    ->helperText('Mark if the reviewer wants to remain anonymous'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('helpful_votes')
                                    ->label('Helpful Votes')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Number of users who found this review helpful'),

                                Forms\Components\TextInput::make('total_votes')
                                    ->label('Total Votes')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Total number of votes received'),
                            ]),

                        Forms\Components\Select::make('status')
                            ->label('Review Status')
                            ->options([
                                'active' => 'Active',
                                'pending' => 'Pending Review',
                                'rejected' => 'Rejected',
                                'hidden' => 'Hidden',
                            ])
                            ->default('active')
                            ->required()
                            ->helperText('Current status of the review'),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(2)
                            ->placeholder('Internal notes for administrators...')
                            ->columnSpanFull(),
                    ]),
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

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Reviewer')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->user?->email)
                    ->visible(fn ($record) => $record && !$record->is_anonymous),

                Tables\Columns\TextColumn::make('anonymous_reviewer')
                    ->label('Reviewer')
                    ->formatStateUsing(fn () => 'Anonymous User')
                    ->visible(fn ($record) => $record && $record->is_anonymous)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('★', $state) . str_repeat('☆', 5 - $state) . " ({$state}/5)")
                    ->sortable()
                    ->color(fn ($state) => match(true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(30)
                    ->description(fn ($record) => $record->comment ? \Illuminate\Support\Str::limit($record->comment, 50) : null),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'hidden' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('helpful_votes')
                    ->label('Helpful')
                    ->numeric()
                    ->sortable()
                    ->description(fn ($record) => "of {$record->total_votes} votes")
                    ->color('success'),

                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Visit Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Review Date')
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

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Reviewer'),

                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        '1' => '1 Star',
                        '2' => '2 Stars',
                        '3' => '3 Stars',
                        '4' => '4 Stars',
                        '5' => '5 Stars',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'rejected' => 'Rejected',
                        'hidden' => 'Hidden',
                    ]),

                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified Reviews'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured Reviews'),

                Tables\Filters\TernaryFilter::make('is_anonymous')
                    ->label('Anonymous Reviews'),

                Tables\Filters\Filter::make('recent')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->action(fn ($record) => $record->update(['is_verified' => true]))
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record && !$record->is_verified),
                Tables\Actions\Action::make('feature')
                    ->label('Feature')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->action(fn ($record) => $record->update(['is_featured' => true]))
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record && !$record->is_featured),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('verify')
                        ->label('Mark as Verified')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_verified' => true])))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Mark as Featured')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_featured' => true])))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['status' => 'active'])))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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

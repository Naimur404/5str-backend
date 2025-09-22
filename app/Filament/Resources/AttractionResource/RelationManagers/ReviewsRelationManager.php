<?php

namespace App\Filament\Resources\AttractionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Review Details')
                    ->schema([
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

                        Forms\Components\Toggle::make('is_anonymous')
                            ->label('Anonymous Review')
                            ->helperText('Check if the reviewer wants to remain anonymous'),
                    ])->columns(3),

                Forms\Components\Section::make('Review Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Review Title')
                            ->maxLength(255)
                            ->placeholder('Enter a title for this review'),

                        Forms\Components\Textarea::make('comment')
                            ->label('Review Comment')
                            ->required()
                            ->rows(4)
                            ->placeholder('Write the detailed review here...')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\DateTimePicker::make('visit_date')
                            ->label('Visit Date')
                            ->helperText('When did the user visit this attraction?'),

                        Forms\Components\TagsInput::make('experience_tags')
                            ->label('Experience Tags')
                            ->placeholder('Add tags like "family-friendly", "romantic", "adventure"')
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
                            ]),

                        Forms\Components\KeyValue::make('visit_info')
                            ->label('Visit Information')
                            ->keyLabel('Detail')
                            ->valueLabel('Information')
                            ->helperText('Additional visit details')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Review Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'pending' => 'Pending Review',
                                'rejected' => 'Rejected',
                                'hidden' => 'Hidden',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Toggle::make('is_verified')
                            ->label('Verified Review')
                            ->helperText('Mark if this review is verified'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Review')
                            ->helperText('Mark if this review should be featured'),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(2)
                            ->placeholder('Internal notes...')
                            ->columnSpanFull(),
                    ])->columns(3)->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
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
                    ->description(fn ($record) => $record->comment ? \Illuminate\Support\Str::limit($record->comment, 60) : null),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'hidden' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified')
                    ->trueColor('success'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured')
                    ->trueColor('warning'),

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
                    ->label('Posted')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
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
                    ->label('Verified'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\TernaryFilter::make('is_anonymous')
                    ->label('Anonymous'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Review'),
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
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['status' => 'active'])))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('verify')
                        ->label('Verify Selected')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['is_verified' => true])))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Refresh table every 30 seconds
    }
}

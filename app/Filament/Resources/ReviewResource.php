<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Filament\Resources\ReviewResource\RelationManagers;
use App\Models\Review;
use App\Models\User;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Attraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    
    protected static ?string $navigationGroup = 'Content Management';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // If user is business-owner, only show reviews for their businesses
        if (Auth::user() && Auth::user()->hasRole('business-owner')) {
            $userBusinessIds = Business::where('owner_user_id', Auth::id())->pluck('id');
            $userOfferingIds = BusinessOffering::whereIn('business_id', $userBusinessIds)->pluck('id');
            
            $query->where(function ($q) use ($userBusinessIds, $userOfferingIds) {
                $q->where(function ($subQ) use ($userBusinessIds) {
                    $subQ->where('reviewable_type', 'App\\Models\\Business')
                         ->whereIn('reviewable_id', $userBusinessIds);
                })->orWhere(function ($subQ) use ($userOfferingIds) {
                    $subQ->where('reviewable_type', 'App\\Models\\BusinessOffering')
                         ->whereIn('reviewable_id', $userOfferingIds);
                });
            });
        }
        
        return $query;
    }

    public static function canCreate(): bool
    {
        // Only admins can create reviews through admin panel
        return Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']);
    }

    public static function canEdit($record): bool
    {
        // Business owners cannot edit reviews, only view
        return Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Review Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->options(User::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('reviewable_type')
                            ->label('Review Type')
                            ->options([
                                'App\\Models\\Business' => 'Business',
                                'App\\Models\\BusinessOffering' => 'Business Offering',
                                'App\\Models\\Attraction' => 'Attraction',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('reviewable_id')
                            ->label('Business/Offering/Attraction')
                            ->options(function (callable $get) {
                                $type = $get('reviewable_type');
                                if ($type === 'App\\Models\\Business') {
                                    return Business::all()->pluck('business_name', 'id');
                                } elseif ($type === 'App\\Models\\BusinessOffering') {
                                    return BusinessOffering::all()->pluck('name', 'id');
                                } elseif ($type === 'App\\Models\\Attraction') {
                                    return Attraction::all()->pluck('name', 'id');
                                }
                                return [];
                            })
                            ->required()
                            ->searchable(),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Ratings')
                    ->schema([
                        Forms\Components\TextInput::make('overall_rating')
                            ->label('Overall Rating')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->required()
                            ->step(1),
                        Forms\Components\KeyValue::make('ratings')
                            ->label('Sub-Ratings')
                            ->keyLabel('Criteria')
                            ->valueLabel('Rating (1-5)')
                            ->columnSpan(2),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Review Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255)
                            ->required(),
                    ])->columns(1),
                    
                Forms\Components\Section::make('Review Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_recommended')
                            ->label('Recommend to others')
                            ->default(true),
                    ])->columns(1),
                    
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\TextInput::make('helpful_count')
                            ->label('Helpful Count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('not_helpful_count')
                            ->label('Not Helpful Count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewable.business_name')
                    ->label('Business/Item/Attraction')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        if ($record->reviewable_type === 'App\\Models\\Business') {
                            return $record->reviewable->business_name ?? 'N/A';
                        } else {
                            return $record->reviewable->name ?? 'N/A';
                        }
                    }),
                Tables\Columns\TextColumn::make('overall_rating')
                    ->label('Rating')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn ($record) => $record->update(['status' => 'approved']))
                    ->visible(fn ($record) => $record->status !== 'approved' && 
                              Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(fn ($record) => $record->update(['status' => 'rejected']))
                    ->visible(fn ($record) => $record->status !== 'rejected' && 
                              Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'approved']))
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                    Tables\Actions\BulkAction::make('reject')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status' => 'rejected']))
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReviews::route('/'),
        ];
    }
}

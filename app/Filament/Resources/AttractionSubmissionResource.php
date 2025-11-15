<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttractionSubmissionResource\Pages;
use App\Models\AttractionSubmission;
use App\Models\Attraction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class AttractionSubmissionResource extends Resource
{
    protected static ?string $model = AttractionSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';
    
    protected static ?string $navigationLabel = 'Attraction Submissions';
    
    protected static ?string $modelLabel = 'Attraction Submission';
    
    protected static ?string $pluralModelLabel = 'Attraction Submissions';
    
    protected static ?string $navigationGroup = 'Submissions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Attraction Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\TextInput::make('type'),
                        Forms\Components\Textarea::make('address'),
                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->placeholder('Enter city name'),
                        Forms\Components\TextInput::make('entry_fee')
                            ->numeric(),
                        Forms\Components\TextInput::make('best_time_to_visit'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric(),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Review')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->placeholder('Add notes about your decision...'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Submitted By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\ImageColumn::make('images')
                    ->label('Images')
                    ->getStateUsing(function ($record) {
                        $images = json_decode($record->images, true);
                        return $images ? Storage::url($images[0]) : null;
                    })
                    ->size(60),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
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
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'historical' => 'Historical',
                        'natural' => 'Natural',
                        'cultural' => 'Cultural',
                        'entertainment' => 'Entertainment',
                        'religious' => 'Religious',
                        'educational' => 'Educational',
                        'recreational' => 'Recreational',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        self::approveSubmission($record);
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('Explain why this submission is being rejected...'),
                    ])
                    ->action(function ($record, array $data) {
                        self::rejectSubmission($record, $data['admin_notes']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    self::approveSubmission($record);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttractionSubmissions::route('/'),
            'view' => Pages\ViewAttractionSubmission::route('/{record}'),
            'edit' => Pages\EditAttractionSubmission::route('/{record}/edit'),
        ];
    }
    
    /**
     * Approve an attraction submission and create the attraction in main table
     */
    public static function approveSubmission(AttractionSubmission $submission)
    {
        try {
            // Create attraction in main table with proper field mapping
            $attraction = Attraction::create([
                'name' => $submission->name,
                'slug' => \Illuminate\Support\Str::slug($submission->name),
                'description' => $submission->description,
                'type' => $submission->type,
                'category' => $submission->type, // Use type as category
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'address' => $submission->address,
                'city' => $submission->city ?? self::extractCityFromAddress($submission->address),
                'area' => self::extractAreaFromAddress($submission->address),
                'district' => self::extractCityFromAddress($submission->address),
                'country' => 'Bangladesh', // Default country
                'is_free' => $submission->entry_fee == 0,
                'entry_fee' => $submission->entry_fee,
                'currency' => 'BDT',
                'opening_hours' => $submission->visiting_hours,
                'facilities' => $submission->facilities,
                'best_time_to_visit' => $submission->best_time_to_visit,
                'estimated_duration_minutes' => 120, // Default 2 hours
                'overall_rating' => 0.0,
                'total_reviews' => 0,
                'total_likes' => 0,
                'total_dislikes' => 0,
                'total_shares' => 0,
                'is_active' => true,
                'is_featured' => false,
                'status' => 'published',
            ]);
            
            // Handle images if they exist
            if ($submission->images) {
                $images = json_decode($submission->images, true);
                foreach ($images as $imagePath) {
                    // Move from submission folder to attraction folder
                    $newPath = str_replace('attraction_submissions/', 'attractions/', $imagePath);
                    if (Storage::disk('public')->exists($imagePath)) {
                        Storage::disk('public')->copy($imagePath, $newPath);
                    }
                }
            }
            
            // Update submission status
            $submission->update([
                'status' => 'approved',
                'admin_notes' => 'Approved and added to main attraction listing',
                'reviewed_at' => now(),
                'approved_attraction_id' => $attraction->id,
            ]);
            
            Notification::make()
                ->title('Attraction Approved Successfully')
                ->body("Attraction '{$submission->name}' has been approved and added to the main listing.")
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Approval Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    /**
     * Reject an attraction submission
     */
    public static function rejectSubmission(AttractionSubmission $submission, string $reason)
    {
        $submission->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
            'reviewed_at' => now(),
        ]);
        
        Notification::make()
            ->title('Attraction Rejected')
            ->body("Attraction '{$submission->name}' has been rejected.")
            ->warning()
            ->send();
    }
    
    /**
     * Extract city from address string
     */
    private static function extractCityFromAddress(string $address)
    {
        // Simple extraction - you might want to improve this logic
        $parts = explode(',', $address);
        return count($parts) > 1 ? trim($parts[count($parts) - 1]) : 'Unknown';
    }
    
    /**
     * Extract area from address string
     */
    private static function extractAreaFromAddress(string $address)
    {
        // Simple extraction - you might want to improve this logic
        $parts = explode(',', $address);
        return count($parts) > 2 ? trim($parts[count($parts) - 2]) : 'Unknown';
    }
}

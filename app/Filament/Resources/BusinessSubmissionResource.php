<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessSubmissionResource\Pages;
use App\Filament\Resources\BusinessSubmissionResource\RelationManagers;
use App\Models\BusinessSubmission;
use App\Models\Business;
use App\Models\User;
use App\Models\Category;
use App\Traits\AwardsSubmissionPoints;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BusinessSubmissionResource extends Resource
{
    use AwardsSubmissionPoints;
    protected static ?string $model = BusinessSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationLabel = 'Business Submissions';
    
    protected static ?string $modelLabel = 'Business Submission';
    
    protected static ?string $pluralModelLabel = 'Business Submissions';
    
    protected static ?string $navigationGroup = 'Submissions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Business Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\TextInput::make('category'),
                        Forms\Components\Textarea::make('address'),
                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->placeholder('Enter city name'),
                        Forms\Components\TextInput::make('phone'),
                        Forms\Components\TextInput::make('email')
                            ->email(),
                        Forms\Components\TextInput::make('website')
                            ->url(),
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
                Tables\Columns\TextColumn::make('category')
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
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\Filter::make('recent')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7)))
                    ->label('Last 7 days'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinessSubmissions::route('/'),
            'view' => Pages\ViewBusinessSubmission::route('/{record}'),
            'edit' => Pages\EditBusinessSubmission::route('/{record}/edit'),
        ];
    }
    
    /**
     * Approve a business submission and create the business in main table
     */
    public static function approveSubmission(BusinessSubmission $submission)
    {
        try {
                        // Create business in main table with proper field mapping
            $business = Business::create([
                'business_name' => $submission->name,
                'slug' => \Illuminate\Support\Str::slug($submission->name),
                'description' => $submission->description,
                'category_id' => 1, // Default category - you may want to map this based on submission->category
                'business_email' => $submission->email,
                'business_phone' => $submission->phone,
                'website_url' => $submission->website,
                'full_address' => $submission->address,
                'city' => $submission->city ?? self::extractCityFromAddress($submission->address),
                'area' => self::extractAreaFromAddress($submission->address),
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'opening_hours' => $submission->opening_hours,
                'price_range' => 1, // Default price range
                'has_delivery' => false,
                'has_pickup' => false,
                'has_parking' => false,
                'is_verified' => true,
                'is_featured' => false,
                'is_active' => true,
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'overall_rating' => 0.0,
                'total_reviews' => 0,
                'discovery_score' => 50.0,
            ]);
            
            // Handle images if they exist
            if ($submission->images) {
                $images = json_decode($submission->images, true);
                foreach ($images as $imagePath) {
                    // Move from submission folder to business folder
                    $newPath = str_replace('business_submissions/', 'businesses/', $imagePath);
                    if (Storage::disk('public')->exists($imagePath)) {
                        Storage::disk('public')->copy($imagePath, $newPath);
                        
                        // Create gallery entry (if you have a business gallery model)
                        // BusinessGallery::create([
                        //     'business_id' => $business->id,
                        //     'image_path' => $newPath,
                        // ]);
                    }
                }
            }
            
            // Update submission status
            $submission->update([
                'status' => 'approved',
                'admin_notes' => 'Approved and added to main business listing',
                'reviewed_at' => now(),
                'approved_business_id' => $business->id,
            ]);
            
            // Award points to the user for approved submission
            self::awardSubmissionPoints($submission);
            
            Notification::make()
                ->title('Business Approved Successfully')
                ->body("Business '{$submission->name}' has been approved and added to the main listing.")
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
     * Reject a business submission
     */
    public static function rejectSubmission(BusinessSubmission $submission, string $reason)
    {
        $submission->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
            'reviewed_at' => now(),
        ]);
        
        Notification::make()
            ->title('Business Rejected')
            ->body("Business '{$submission->name}' has been rejected.")
            ->warning()
            ->send();
    }
    
    /**
     * Get category ID by name (create if doesn't exist)
     */
    private static function getCategoryId(string $categoryName)
    {
        $category = Category::where('name', $categoryName)->first();
        
        if (!$category) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => \Illuminate\Support\Str::slug($categoryName),
                'description' => 'Auto-created from submission',
            ]);
        }
        
        return $category->id;
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

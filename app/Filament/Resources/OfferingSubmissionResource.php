<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferingSubmissionResource\Pages;
use App\Models\OfferingSubmission;
use App\Models\BusinessOffering;
use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class OfferingSubmissionResource extends Resource
{
    protected static ?string $model = OfferingSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    
    protected static ?string $navigationLabel = 'Offering Submissions';
    
    protected static ?string $modelLabel = 'Offering Submission';
    
    protected static ?string $pluralModelLabel = 'Offering Submissions';
    
    protected static ?string $navigationGroup = 'Submissions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Business Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_name'),
                        Forms\Components\Textarea::make('business_address'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Offering Information')
                    ->schema([
                        Forms\Components\TextInput::make('offering_name')
                            ->required(),
                        Forms\Components\Textarea::make('offering_description'),
                        Forms\Components\TextInput::make('offering_category'),
                        Forms\Components\TextInput::make('price')
                            ->numeric(),
                        Forms\Components\Select::make('price_type')
                            ->options([
                                'fixed' => 'Fixed',
                                'range' => 'Range',
                                'negotiable' => 'Negotiable',
                                'free' => 'Free',
                            ]),
                        Forms\Components\TextInput::make('availability'),
                        Forms\Components\TextInput::make('contact_info'),
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
                Tables\Columns\TextColumn::make('offering_name')
                    ->label('Offering Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Business')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offering_category')
                    ->label('Category')
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
                Tables\Columns\TextColumn::make('price')
                    ->money('BDT')
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
                Tables\Filters\SelectFilter::make('price_type')
                    ->options([
                        'fixed' => 'Fixed',
                        'range' => 'Range',
                        'negotiable' => 'Negotiable',
                        'free' => 'Free',
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
            'index' => Pages\ListOfferingSubmissions::route('/'),
            'view' => Pages\ViewOfferingSubmission::route('/{record}'),
            'edit' => Pages\EditOfferingSubmission::route('/{record}/edit'),
        ];
    }
    
    /**
     * Approve an offering submission and create the offering in main table
     */
    public static function approveSubmission(OfferingSubmission $submission)
    {
        try {
            $business = null;
            
            // First try to find business by ID if provided
            if ($submission->business_id) {
                $business = Business::find($submission->business_id);
            }
            
            // If not found by ID, try to find by name and address
            if (!$business) {
                $business = Business::where('business_name', 'like', '%' . $submission->business_name . '%')
                    ->orWhere('full_address', 'like', '%' . $submission->business_address . '%')
                    ->first();
            }
                
            if (!$business) {
                Notification::make()
                    ->title('Business Not Found')
                    ->body('Could not find a matching business for this offering. Please create the business first.')
                    ->danger()
                    ->send();
                return;
            }
            
            // Create offering in main table
            $offering = BusinessOffering::create([
                'business_id' => $business->id,
                'name' => $submission->offering_name,
                'description' => $submission->offering_description,
                'category' => $submission->offering_category,
                'price' => $submission->price,
                'price_type' => $submission->price_type,
                'availability' => $submission->availability,
                'contact_info' => $submission->contact_info,
                'status' => 'active',
                'submitted_by_user_id' => $submission->user_id,
            ]);
            
            // Handle images if they exist
            if ($submission->images) {
                $images = json_decode($submission->images, true);
                foreach ($images as $imagePath) {
                    // Move from submission folder to offering folder
                    $newPath = str_replace('offering_submissions/', 'offerings/', $imagePath);
                    if (Storage::disk('public')->exists($imagePath)) {
                        Storage::disk('public')->copy($imagePath, $newPath);
                    }
                }
            }
            
            // Update submission status
            $submission->update([
                'status' => 'approved',
                'admin_notes' => 'Approved and added to main offering listing',
                'reviewed_at' => now(),
                'approved_offering_id' => $offering->id,
            ]);
            
            Notification::make()
                ->title('Offering Approved Successfully')
                ->body("Offering '{$submission->offering_name}' has been approved and added to {$business->name}.")
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
     * Reject an offering submission
     */
    public static function rejectSubmission(OfferingSubmission $submission, string $reason)
    {
        $submission->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
            'reviewed_at' => now(),
        ]);
        
        Notification::make()
            ->title('Offering Rejected')
            ->body("Offering '{$submission->offering_name}' has been rejected.")
            ->warning()
            ->send();
    }
}

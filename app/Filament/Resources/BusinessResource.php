<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessResource\Pages;
use App\Filament\Resources\BusinessResource\RelationManagers;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    
    protected static ?string $navigationGroup = 'Business Management';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // If user is business-owner, only show their own businesses
        if (Auth::user() && Auth::user()->hasRole('business-owner')) {
            $query->where('owner_user_id', Auth::id());
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation !== 'create') {
                                    return;
                                }
                                $set('slug', Str::slug($state));
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from business name, but can be customized')
                            ->dehydrateStateUsing(fn ($state) => Str::slug($state)),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(Category::where('parent_id', null)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('subcategory_id', null)),
                        Forms\Components\Select::make('subcategory_id')
                            ->label('Subcategory')
                            ->options(function (Forms\Get $get) {
                                $categoryId = $get('category_id');
                                if (!$categoryId) {
                                    return [];
                                }
                                return Category::where('parent_id', $categoryId)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Select subcategory (optional)')
                            ->helperText('Choose a subcategory if applicable'),
                        Forms\Components\Select::make('owner_user_id')
                            ->label('Owner')
                            ->options(User::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('business_phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('website_url')
                            ->url()
                            ->maxLength(255),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Location Details')
                    ->schema([
                        Forms\Components\Textarea::make('full_address')
                            ->required()
                            ->maxLength(500)
                            ->rows(2),
                        Forms\Components\TextInput::make('latitude')
                            ->required()
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.00000001)
                            ->placeholder('e.g., 23.7465'),
                        Forms\Components\TextInput::make('longitude')
                            ->required()
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.00000001)
                            ->placeholder('e.g., 90.3754'),
                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('area')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('landmark')
                            ->maxLength(255)
                            ->placeholder('Optional landmark'),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Business Hours')
                    ->schema([
                        Forms\Components\KeyValue::make('opening_hours')
                            ->label('Opening Hours')
                            ->keyLabel('Day of Week')
                            ->valueLabel('Hours (e.g., 9:00 AM - 6:00 PM)')
                            ->addActionLabel('Add Day')
                            ->helperText('Enter opening hours for each day. Use format like "9:00 AM - 6:00 PM" or "Closed"')
                            ->default([
                                'monday' => '9:00 AM - 6:00 PM',
                                'tuesday' => '9:00 AM - 6:00 PM',
                                'wednesday' => '9:00 AM - 6:00 PM',
                                'thursday' => '9:00 AM - 6:00 PM',
                                'friday' => '9:00 AM - 6:00 PM',
                                'saturday' => '10:00 AM - 4:00 PM',
                                'sunday' => 'Closed',
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Business Features')
                    ->schema([
                        Forms\Components\Select::make('price_range')
                            ->label('Price Range')
                            ->options([
                                1 => '$ (Budget)',
                                2 => '$$ (Moderate)',
                                3 => '$$$ (Expensive)',
                                4 => '$$$$ (Very Expensive)',
                            ])
                            ->required()
                            ->default(1),
                        Forms\Components\Toggle::make('has_delivery')
                            ->label('Offers Delivery')
                            ->default(false),
                        Forms\Components\Toggle::make('has_pickup')
                            ->label('Offers Pickup')
                            ->default(false),
                        Forms\Components\Toggle::make('has_parking')
                            ->label('Has Parking')
                            ->default(false),
                    ])->columns(4),

                Forms\Components\Section::make('National Business Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_national')
                            ->label('National Business')
                            ->helperText('Check if this business operates nationwide (like Pran Foods, Polar Ice Cream, etc.)')
                            ->default(false)
                            ->live(),
                        Forms\Components\Select::make('service_coverage')
                            ->label('Service Coverage')
                            ->options([
                                'local' => 'Local (Specific Area)',
                                'citywide' => 'City-wide',
                                'regional' => 'Regional (Multiple Districts)',
                                'national' => 'National (Entire Country)',
                            ])
                            ->default('local')
                            ->helperText('Geographic scope of business operations')
                            ->visible(fn (Forms\Get $get): bool => $get('is_national') === true),
                        Forms\Components\Select::make('business_model')
                            ->label('Business Model')
                            ->options([
                                'retail' => 'Retail Store',
                                'restaurant' => 'Restaurant/Food Service',
                                'service' => 'Service Provider',
                                'manufacturing' => 'Manufacturing',
                                'brand' => 'Brand/Chain',
                                'franchise' => 'Franchise',
                                'distributor' => 'Distributor',
                                'wholesaler' => 'Wholesaler',
                            ])
                            ->default('retail')
                            ->helperText('Primary business model or type')
                            ->visible(fn (Forms\Get $get): bool => $get('is_national') === true),
                        Forms\Components\TagsInput::make('service_areas')
                            ->label('Service Areas')
                            ->placeholder('Add cities/regions served')
                            ->helperText('List the major cities or regions this business serves (e.g., Dhaka, Chittagong, Sylhet)')
                            ->suggestions([
                                'Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barisal', 'Rangpur', 
                                'Mymensingh', 'Comilla', 'Gazipur', 'Narayanganj', 'Tangail', 'Jessore', 
                                'Bogra', 'Pabna', 'Dinajpur', 'Cox\'s Bazar', 'Faridpur', 'Brahmanbaria'
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('is_national') === true && in_array($get('service_coverage'), ['regional', 'national'])),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->description('Configure settings for national brands and chains that operate across multiple locations'),
                    
                Forms\Components\Section::make('Images')
                    ->schema([
                        Forms\Components\Placeholder::make('images_note')
                            ->label('')
                            ->content('Business images are managed separately through the "Images" tab. After saving this business, you can add logo, cover, and gallery images using the dedicated image management interface.')
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Status & Verification')
                    ->schema([
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Verified Business')
                            ->default(false)
                            ->helperText('Mark as verified after admin review')
                            ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Business')
                            ->default(false)
                            ->helperText('Featured businesses appear prominently')
                            ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator'])),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive businesses are hidden from public'),
                    ])->columns(3)
                    ->visibleOn(['create', 'edit']),
                    
                Forms\Components\Section::make('Approval Status')
                    ->schema([
                        Forms\Components\Select::make('approval_status')
                            ->label('Approval Status')
                            ->options([
                                'approved' => 'Approved',
                                'pending' => 'Pending Review',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->disabled(fn () => Auth::user()?->hasRole('business-owner'))
                            ->helperText('Only admins can change approval status'),
                        Forms\Components\KeyValue::make('pending_changes')
                            ->label('Pending Changes')
                            ->keyLabel('Field')
                            ->valueLabel('New Value')
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->pending_changes),
                        Forms\Components\Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approver', 'name')
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->approved_by),
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Approved At')
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->approved_at),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->helperText('Internal notes for approval process')
                            ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                            ->maxLength(500),
                    ])->columns(2)
                    ->visibleOn('edit')
                    ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']) || 
                              (Auth::user()?->hasRole('business-owner') && 
                               $form->getRecord()?->approval_status === 'pending')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('subcategory.name')
                    ->label('Subcategory')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No Owner'),
                Tables\Columns\TextColumn::make('business_email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('business_phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('area')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price_range')
                    ->label('Price Range')
                    ->formatStateUsing(fn ($state) => match($state) {
                        1 => '$ (Budget)',
                        2 => '$$ (Moderate)',
                        3 => '$$$ (Expensive)',
                        4 => '$$$$ (Very Expensive)',
                        default => 'N/A'
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        1 => 'success',
                        2 => 'warning',
                        3 => 'danger',
                        4 => 'gray',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('overall_rating')
                    ->label('Rating')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . ' ⭐'),
                Tables\Columns\TextColumn::make('total_reviews')
                    ->label('Reviews')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discovery_score')
                    ->label('Discovery Score')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 1))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('has_delivery')
                    ->label('Delivery')
                    ->boolean()
                    ->trueIcon('heroicon-o-truck')
                    ->falseIcon('heroicon-o-x-mark')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_pickup')
                    ->label('Pickup')
                    ->boolean()
                    ->trueIcon('heroicon-o-building-storefront')
                    ->falseIcon('heroicon-o-x-mark')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_parking')
                    ->label('Parking')
                    ->boolean()
                    ->trueIcon('heroicon-o-square-3-stack-3d')
                    ->falseIcon('heroicon-o-x-mark')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_national')
                    ->label('National')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-asia-australia')
                    ->falseIcon('heroicon-o-building-office')
                    ->tooltip(fn (Business $record): string => $record->is_national ? 'National Business' : 'Local Business'),
                Tables\Columns\TextColumn::make('service_coverage')
                    ->label('Coverage')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'national' => 'success',
                        'regional' => 'info', 
                        'citywide' => 'warning',
                        'local' => 'gray',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'local' => 'Local',
                        'citywide' => 'City-wide',
                        'regional' => 'Regional',
                        'national' => 'National',
                        default => $state ? ucfirst($state) : 'Local'
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('business_model')
                    ->label('Model')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'retail' => 'Retail',
                        'restaurant' => 'Restaurant',
                        'service' => 'Service',
                        'manufacturing' => 'Manufacturing',
                        'brand' => 'Brand',
                        'franchise' => 'Franchise',
                        'distributor' => 'Distributor',
                        'wholesaler' => 'Wholesaler',
                        default => $state ? ucfirst($state) : 'Retail'
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'pending')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
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
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('subcategory')
                    ->relationship('subcategory', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('owner')
                    ->relationship('owner', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('city')
                    ->options(fn () => Business::distinct()->pluck('city', 'city')->filter())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('price_range')
                    ->options([
                        1 => '$ (Budget)',
                        2 => '$$ (Moderate)',
                        3 => '$$$ (Expensive)',
                        4 => '$$$$ (Very Expensive)',
                    ]),
                Tables\Filters\TernaryFilter::make('has_delivery')
                    ->label('Offers Delivery'),
                Tables\Filters\TernaryFilter::make('has_pickup')
                    ->label('Offers Pickup'),
                Tables\Filters\TernaryFilter::make('has_parking')
                    ->label('Has Parking'),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending Review',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_national')
                    ->label('National Business'),
                Tables\Filters\SelectFilter::make('service_coverage')
                    ->label('Service Coverage')
                    ->options([
                        'local' => 'Local',
                        'citywide' => 'City-wide', 
                        'regional' => 'Regional',
                        'national' => 'National',
                    ]),
                Tables\Filters\SelectFilter::make('business_model')
                    ->label('Business Model')
                    ->options([
                        'retail' => 'Retail Store',
                        'restaurant' => 'Restaurant',
                        'service' => 'Service Provider',
                        'manufacturing' => 'Manufacturing',
                        'brand' => 'Brand/Chain',
                        'franchise' => 'Franchise',
                        'distributor' => 'Distributor',
                        'wholesaler' => 'Wholesaler',
                    ]),
                Tables\Filters\Filter::make('rating')
                    ->form([
                        Forms\Components\TextInput::make('min_rating')
                            ->label('Minimum Rating')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['min_rating'],
                            fn ($query, $rating) => $query->where('overall_rating', '>=', $rating)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('viewLocation')
                    ->label('View on Map')
                    ->icon('heroicon-o-map-pin')
                    ->url(fn (Business $record) => "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Businesses')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'approval_status' => 'approved',
                                    'approved_by' => Auth::id(),
                                    'approved_at' => now(),
                                    'pending_changes' => null,
                                ]);
                            }
                        }),
                    Tables\Actions\BulkAction::make('reject')
                        ->label('Reject Businesses')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'approval_status' => 'rejected',
                                    'approved_by' => Auth::id(),
                                    'approved_at' => now(),
                                ]);
                            }
                        }),
                    Tables\Actions\BulkAction::make('verify')
                        ->label('Mark as Verified')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_verified' => true]);
                            }
                        }),
                    Tables\Actions\BulkAction::make('unverify')
                        ->label('Remove Verification')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_verified' => false]);
                            }
                        }),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Mark as Featured')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_featured' => true]);
                            }
                        }),
                    Tables\Actions\BulkAction::make('markNational')
                        ->label('Mark as National')
                        ->icon('heroicon-o-globe-asia-australia')
                        ->color('success')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->form([
                            Forms\Components\Select::make('service_coverage')
                                ->label('Service Coverage')
                                ->options([
                                    'regional' => 'Regional (Multiple Districts)',
                                    'national' => 'National (Entire Country)',
                                ])
                                ->required()
                                ->default('national'),
                            Forms\Components\Select::make('business_model')
                                ->label('Business Model')
                                ->options([
                                    'retail' => 'Retail Store',
                                    'restaurant' => 'Restaurant/Food Service',
                                    'service' => 'Service Provider',
                                    'manufacturing' => 'Manufacturing',
                                    'brand' => 'Brand/Chain',
                                    'franchise' => 'Franchise',
                                    'distributor' => 'Distributor',
                                    'wholesaler' => 'Wholesaler',
                                ])
                                ->required()
                                ->default('brand'),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_national' => true,
                                    'service_coverage' => $data['service_coverage'],
                                    'business_model' => $data['business_model'],
                                ]);
                            }
                        }),
                    Tables\Actions\BulkAction::make('markLocal')
                        ->label('Mark as Local')
                        ->icon('heroicon-o-building-office')
                        ->color('info')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_national' => false,
                                    'service_coverage' => 'local',
                                    'business_model' => 'retail',
                                    'service_areas' => null,
                                ]);
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin'])),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BusinessImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinesses::route('/'),
            'create' => Pages\CreateBusiness::route('/create'),
            'edit' => Pages\EditBusiness::route('/{record}/edit'),
        ];
    }
}

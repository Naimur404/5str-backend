<?php

namespace App\Filament\Resources\BusinessResource\RelationManagers;

use App\Models\Business;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Branches / Locations';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branch Details')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->maxLength(255)
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->business_name . ' - Branch')
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
                            ->unique(table: Business::class, column: 'slug', ignoreRecord: true)
                            ->dehydrateStateUsing(fn ($state) => Str::slug($state)),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->maxLength(1000)
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->description)
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('category_id')
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->category_id),
                        Forms\Components\Hidden::make('subcategory_id')
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->subcategory_id),
                        Forms\Components\Hidden::make('owner_user_id')
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->owner_user_id),
                        Forms\Components\Hidden::make('price_range')
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->price_range),
                    ])->columns(2),

                Forms\Components\Section::make('Location & Contact')
                    ->schema([
                        Forms\Components\TextInput::make('business_phone')
                            ->tel()
                            ->maxLength(255)
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->business_phone),
                        Forms\Components\TextInput::make('business_email')
                            ->email()
                            ->maxLength(255)
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->business_email),
                        Forms\Components\Textarea::make('full_address')
                            ->required()
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->maxLength(255)
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->city),
                        Forms\Components\TextInput::make('area')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('landmark')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('latitude')
                            ->required()
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.00000001),
                        Forms\Components\TextInput::make('longitude')
                            ->required()
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.00000001),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('business_name')
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('area')
                    ->searchable(),
                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('business_phone')
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('overall_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . ' ⭐')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $parent = $livewire->getOwnerRecord();
                        $data['category_id'] = $parent->category_id;
                        $data['subcategory_id'] = $parent->subcategory_id;
                        $data['owner_user_id'] = $parent->owner_user_id;
                        $data['price_range'] = $parent->price_range;
                        $data['approval_status'] = 'approved'; // auto-approve branch if brand is approved
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

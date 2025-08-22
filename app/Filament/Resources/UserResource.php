<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = 'User Management';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user() && Auth::user()->hasAnyRole(['super-admin', 'admin', 'moderator']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('profile_image')
                            ->image()
                            ->directory('profile-images'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\TextInput::make('city')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('current_latitude')
                            ->numeric()
                            ->step('any'),
                        Forms\Components\TextInput::make('current_longitude')
                            ->numeric()
                            ->step('any'),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Account Details')
                    ->schema([
                        Forms\Components\TextInput::make('total_points')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('total_reviews_written')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\Select::make('trust_level')
                            ->options([
                                1 => 'New User',
                                2 => 'Basic',
                                3 => 'Trusted',
                                4 => 'Expert',
                                5 => 'Community Leader'
                            ])
                            ->default(1),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Roles & Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->label('Assign Roles')
                            ->relationship('roles', 'name')
                            ->options(Role::all()->pluck('name', 'id'))
                            ->columns(3)
                            ->helperText('Select the roles for this user'),
                        
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Direct Permissions')
                            ->relationship('permissions', 'name')
                            ->options(Permission::all()->pluck('name', 'id'))
                            ->columns(3)
                            ->helperText('Grant specific permissions directly to this user (in addition to role permissions)'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_image')
                    ->circular()
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_points')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('total_reviews_written')
                    ->label('Reviews')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trust_level')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        '1' => 'New User',
                        '2' => 'Basic',
                        '3' => 'Trusted',
                        '4' => 'Expert',
                        '5' => 'Community Leader',
                        default => 'Unknown'
                    })
                    ->color(fn (string $state): string => match($state) {
                        '1' => 'gray',
                        '2' => 'info',
                        '3' => 'warning',
                        '4' => 'success',
                        '5' => 'primary',
                        default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('trust_level')
                    ->options([
                        1 => 'New User',
                        2 => 'Basic',
                        3 => 'Trusted',
                        4 => 'Expert',
                        5 => 'Community Leader'
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}

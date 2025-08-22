<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    
    protected static ?string $navigationGroup = 'Content Management';
    
    protected static ?string $navigationLabel = 'Notifications';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Recipient')
                            ->options(User::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->helperText('Select the user who will receive this notification'),
                        Forms\Components\Select::make('type')
                            ->label('Notification Type')
                            ->options([
                                'business_approved' => 'Business Approved',
                                'business_rejected' => 'Business Rejected',
                                'review_received' => 'Review Received',
                                'offer_created' => 'Offer Created',
                                'profile_updated' => 'Profile Updated',
                                'system_announcement' => 'System Announcement',
                                'reminder' => 'Reminder',
                                'promotion' => 'Promotion',
                                'alert' => 'Alert',
                            ])
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->label('Priority')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                            ])
                            ->default('medium')
                            ->required(),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Message Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Notification title'),
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->maxLength(1000)
                            ->rows(4)
                            ->placeholder('Notification message content'),
                        Forms\Components\KeyValue::make('data')
                            ->label('Additional Data')
                            ->helperText('Optional key-value pairs for extra data (JSON format)')
                            ->addActionLabel('Add data field'),
                    ]),
                    
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_read')
                            ->label('Mark as Read')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('read_at')
                            ->label('Read At')
                            ->disabled()
                            ->native(false)
                            ->helperText('Automatically set when marked as read'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'business_approved' => 'success',
                        'business_rejected' => 'danger',
                        'review_received' => 'info',
                        'offer_created' => 'warning',
                        'system_announcement' => 'primary',
                        'alert' => 'danger',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->message;
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('is_read')
                    ->label('Read')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read At')
                    ->dateTime()
                    ->placeholder('Unread')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'business_approved' => 'Business Approved',
                        'business_rejected' => 'Business Rejected',
                        'review_received' => 'Review Received',
                        'offer_created' => 'Offer Created',
                        'profile_updated' => 'Profile Updated',
                        'system_announcement' => 'System Announcement',
                        'reminder' => 'Reminder',
                        'promotion' => 'Promotion',
                        'alert' => 'Alert',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ]),
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Read Status'),
            ])
            ->actions([
                Tables\Actions\Action::make('markAsRead')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->action(function (Notification $record) {
                        $record->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                    })
                    ->visible(fn (Notification $record) => !$record->is_read),
                Tables\Actions\Action::make('markAsUnread')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->action(function (Notification $record) {
                        $record->update([
                            'is_read' => false,
                            'read_at' => null,
                        ]);
                    })
                    ->visible(fn (Notification $record) => $record->is_read),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAsRead')
                        ->label('Mark as Read')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_read' => true,
                                    'read_at' => now(),
                                ]);
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNotifications::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\BusinessSubmissionResource\Pages;

use App\Filament\Resources\BusinessSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBusinessSubmission extends EditRecord
{
    protected static string $resource = BusinessSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function () {
                    BusinessSubmissionResource::approveSubmission($this->record);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending')
                ->form([
                    \Filament\Forms\Components\Textarea::make('admin_notes')
                        ->label('Rejection Reason')
                        ->required()
                        ->placeholder('Explain why this submission is being rejected...'),
                ])
                ->action(function (array $data) {
                    BusinessSubmissionResource::rejectSubmission($this->record, $data['admin_notes']);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
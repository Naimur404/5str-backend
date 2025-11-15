<?php

namespace App\Filament\Resources\AttractionSubmissionResource\Pages;

use App\Filament\Resources\AttractionSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAttractionSubmission extends ViewRecord
{
    protected static string $resource = AttractionSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function () {
                    AttractionSubmissionResource::approveSubmission($this->record);
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
                    AttractionSubmissionResource::rejectSubmission($this->record, $data['admin_notes']);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
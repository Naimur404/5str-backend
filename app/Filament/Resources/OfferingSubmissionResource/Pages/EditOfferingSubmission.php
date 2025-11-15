<?php

namespace App\Filament\Resources\OfferingSubmissionResource\Pages;

use App\Filament\Resources\OfferingSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferingSubmission extends EditRecord
{
    protected static string $resource = OfferingSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
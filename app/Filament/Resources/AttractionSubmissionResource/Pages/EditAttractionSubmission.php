<?php

namespace App\Filament\Resources\AttractionSubmissionResource\Pages;

use App\Filament\Resources\AttractionSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttractionSubmission extends EditRecord
{
    protected static string $resource = AttractionSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
<?php

namespace App\Filament\Resources\UserAttractionInteractionResource\Pages;

use App\Filament\Resources\UserAttractionInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserAttractionInteraction extends EditRecord
{
    protected static string $resource = UserAttractionInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

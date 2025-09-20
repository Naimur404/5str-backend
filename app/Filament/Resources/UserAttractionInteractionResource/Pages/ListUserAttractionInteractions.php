<?php

namespace App\Filament\Resources\UserAttractionInteractionResource\Pages;

use App\Filament\Resources\UserAttractionInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserAttractionInteractions extends ListRecords
{
    protected static string $resource = UserAttractionInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

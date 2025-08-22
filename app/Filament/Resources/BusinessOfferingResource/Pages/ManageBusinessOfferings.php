<?php

namespace App\Filament\Resources\BusinessOfferingResource\Pages;

use App\Filament\Resources\BusinessOfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBusinessOfferings extends ManageRecords
{
    protected static string $resource = BusinessOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

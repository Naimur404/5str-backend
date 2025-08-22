<?php

namespace App\Filament\Resources\OfferingVariantResource\Pages;

use App\Filament\Resources\OfferingVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOfferingVariants extends ManageRecords
{
    protected static string $resource = OfferingVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

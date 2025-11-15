<?php

namespace App\Filament\Resources\OfferingSubmissionResource\Pages;

use App\Filament\Resources\OfferingSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferingSubmissions extends ListRecords
{
    protected static string $resource = OfferingSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action needed - submissions come from API
        ];
    }
}

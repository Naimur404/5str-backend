<?php

namespace App\Filament\Resources\AttractionSubmissionResource\Pages;

use App\Filament\Resources\AttractionSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttractionSubmissions extends ListRecords
{
    protected static string $resource = AttractionSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action needed - submissions come from API
        ];
    }
}

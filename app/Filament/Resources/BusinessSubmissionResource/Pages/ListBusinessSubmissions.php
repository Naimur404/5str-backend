<?php

namespace App\Filament\Resources\BusinessSubmissionResource\Pages;

use App\Filament\Resources\BusinessSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinessSubmissions extends ListRecords
{
    protected static string $resource = BusinessSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action needed - submissions come from API
        ];
    }
}

<?php

namespace App\Filament\Resources\SearchLogResource\Pages;

use App\Filament\Resources\SearchLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSearchLogs extends ManageRecords
{
    protected static string $resource = SearchLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

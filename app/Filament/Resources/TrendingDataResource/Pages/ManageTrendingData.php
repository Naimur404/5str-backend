<?php

namespace App\Filament\Resources\TrendingDataResource\Pages;

use App\Filament\Resources\TrendingDataResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTrendingData extends ManageRecords
{
    protected static string $resource = TrendingDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

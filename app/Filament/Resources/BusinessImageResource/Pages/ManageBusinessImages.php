<?php

namespace App\Filament\Resources\BusinessImageResource\Pages;

use App\Filament\Resources\BusinessImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBusinessImages extends ManageRecords
{
    protected static string $resource = BusinessImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

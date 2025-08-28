<?php

namespace App\Filament\Resources\EndpointAnalyticsResource\Pages;

use App\Filament\Resources\EndpointAnalyticsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEndpointAnalytics extends EditRecord
{
    protected static string $resource = EndpointAnalyticsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

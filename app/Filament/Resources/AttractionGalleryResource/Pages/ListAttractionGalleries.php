<?php

namespace App\Filament\Resources\AttractionGalleryResource\Pages;

use App\Filament\Resources\AttractionGalleryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttractionGalleries extends ListRecords
{
    protected static string $resource = AttractionGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

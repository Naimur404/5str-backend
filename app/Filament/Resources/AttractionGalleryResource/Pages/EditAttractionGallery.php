<?php

namespace App\Filament\Resources\AttractionGalleryResource\Pages;

use App\Filament\Resources\AttractionGalleryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttractionGallery extends EditRecord
{
    protected static string $resource = AttractionGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

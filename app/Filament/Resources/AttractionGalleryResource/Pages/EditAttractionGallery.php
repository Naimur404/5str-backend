<?php

namespace App\Filament\Resources\AttractionGalleryResource\Pages;

use App\Filament\Resources\AttractionGalleryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditAttractionGallery extends EditRecord
{
    protected static string $resource = AttractionGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If image_path is provided but image_url is empty, use the image_path as image_url
        if (!empty($data['image_path']) && empty($data['image_url'])) {
            // Store the path without /storage prefix - the accessor will handle URL generation
            $data['image_url'] = $data['image_path'];
        }
        
        // If neither image_path nor image_url is provided, throw validation error
        if (empty($data['image_path']) && empty($data['image_url'])) {
            $this->addError('image_url', 'Either upload an image file or provide an external image URL.');
            $this->halt();
        }
        
        return $data;
    }
}

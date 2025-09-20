<?php

namespace App\Filament\Resources\AttractionReviewResource\Pages;

use App\Filament\Resources\AttractionReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttractionReview extends EditRecord
{
    protected static string $resource = AttractionReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

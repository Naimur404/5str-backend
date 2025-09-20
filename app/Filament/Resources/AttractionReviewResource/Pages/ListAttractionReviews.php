<?php

namespace App\Filament\Resources\AttractionReviewResource\Pages;

use App\Filament\Resources\AttractionReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttractionReviews extends ListRecords
{
    protected static string $resource = AttractionReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

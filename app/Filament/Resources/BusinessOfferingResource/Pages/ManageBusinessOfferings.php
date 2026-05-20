<?php

namespace App\Filament\Resources\BusinessOfferingResource\Pages;

use App\Filament\Resources\BusinessOfferingResource;
use App\Models\BusinessOffering;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageBusinessOfferings extends ManageRecords
{
    protected static string $resource = BusinessOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function (BusinessOffering $record) {
                    $business = $record->business()->with('branches:id,parent_id')->first();

                    if (! $business || $business->parent_id !== null) {
                        return;
                    }

                    $branches = $business->branches;
                    if ($branches->isEmpty()) {
                        return;
                    }

                    $template = $record->only([
                        'name', 'description', 'offering_type', 'category_id',
                        'price', 'price_max', 'currency', 'image_url',
                        'is_available', 'is_popular', 'is_featured', 'sort_order',
                        'approval_status', 'approved_by', 'approved_at',
                    ]);

                    foreach ($branches as $branch) {
                        BusinessOffering::create($template + ['business_id' => $branch->id]);
                    }

                    Notification::make()
                        ->title('Offering added to ' . $branches->count() . ' branch(es)')
                        ->success()
                        ->send();
                }),
        ];
    }
}

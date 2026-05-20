<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Models\Offer;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageOffers extends ManageRecords
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function (Offer $record) {
                    $business = $record->business()->with('branches:id,parent_id')->first();

                    if (! $business || $business->parent_id !== null) {
                        return;
                    }

                    $branches = $business->branches;
                    if ($branches->isEmpty()) {
                        return;
                    }

                    $template = $record->only([
                        'title', 'description', 'offer_type',
                        'discount_percentage', 'discount_amount', 'minimum_spend',
                        'offer_code', 'usage_limit',
                        'valid_from', 'valid_to', 'applicable_days',
                        'banner_image', 'is_featured', 'is_active',
                    ]);

                    foreach ($branches as $branch) {
                        Offer::create($template + [
                            'business_id' => $branch->id,
                            'current_usage' => 0,
                        ]);
                    }

                    Notification::make()
                        ->title('Offer rolled out to ' . $branches->count() . ' branch(es)')
                        ->success()
                        ->send();
                }),
        ];
    }
}

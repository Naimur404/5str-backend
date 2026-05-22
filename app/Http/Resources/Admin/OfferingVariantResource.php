<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OfferingVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'offering_id' => $this->offering_id,
            'offering' => $this->whenLoaded('offering', fn () => [
                'id' => $this->offering->id,
                'name' => $this->offering->name,
                'business_id' => $this->offering->business_id,
            ]),
            'variant_name' => $this->variant_name,
            'variant_value' => $this->variant_value,
            'price_adjustment' => $this->price_adjustment !== null ? (float) $this->price_adjustment : null,
            'is_available' => (bool) $this->is_available,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

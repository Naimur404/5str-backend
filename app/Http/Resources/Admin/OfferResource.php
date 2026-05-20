<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'business_name' => $this->business->business_name,
                'parent_id' => $this->business->parent_id,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'offer_type' => $this->offer_type,
            'discount_percentage' => $this->discount_percentage !== null ? (float) $this->discount_percentage : null,
            'discount_amount' => $this->discount_amount !== null ? (float) $this->discount_amount : null,
            'minimum_spend' => $this->minimum_spend !== null ? (float) $this->minimum_spend : null,
            'offer_code' => $this->offer_code,
            'usage_limit' => $this->usage_limit,
            'current_usage' => $this->current_usage,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'applicable_days' => $this->applicable_days,
            'banner_image' => R2Storage::urlFromValue($this->banner_image),
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessOfferingResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'offering_type' => $this->offering_type,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_max' => $this->price_max !== null ? (float) $this->price_max : null,
            'currency' => $this->currency,
            'image_url' => R2Storage::urlFromValue($this->image_url),
            'is_available' => (bool) $this->is_available,
            'is_popular' => (bool) $this->is_popular,
            'is_featured' => (bool) $this->is_featured,
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'sort_order' => $this->sort_order,
            'approval_status' => $this->approval_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

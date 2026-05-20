<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'slug' => $this->slug,
            'description' => $this->description,

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'subcategory' => $this->whenLoaded('subcategory', fn () => $this->subcategory ? [
                'id' => $this->subcategory->id,
                'name' => $this->subcategory->name,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null),
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'owner_user_id' => $this->owner_user_id,

            'business_email' => $this->business_email,
            'business_phone' => $this->business_phone,
            'website_url' => $this->website_url,

            'full_address' => $this->full_address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'city' => $this->city,
            'area' => $this->area,
            'landmark' => $this->landmark,

            'opening_hours' => $this->opening_hours,
            'price_range' => $this->price_range,
            'has_delivery' => (bool) $this->has_delivery,
            'has_pickup' => (bool) $this->has_pickup,
            'has_parking' => (bool) $this->has_parking,

            'is_verified' => (bool) $this->is_verified,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'is_national' => (bool) $this->is_national,
            'service_coverage' => $this->service_coverage,
            'service_areas' => $this->service_areas,
            'business_model' => $this->business_model,

            'product_tags' => $this->product_tags,
            'business_tags' => $this->business_tags,

            'overall_rating' => (float) $this->overall_rating,
            'total_reviews' => $this->total_reviews,
            'discovery_score' => (float) $this->discovery_score,

            'parent_id' => $this->parent_id,
            'is_branch' => $this->parent_id !== null,
            'branches_count' => $this->whenCounted('branches'),
            'parent' => $this->whenLoaded('parentBusiness', fn () => $this->parentBusiness ? [
                'id' => $this->parentBusiness->id,
                'business_name' => $this->parentBusiness->business_name,
            ] : null),
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($b) => [
                'id' => $b->id,
                'business_name' => $b->business_name,
                'slug' => $b->slug,
                'full_address' => $b->full_address,
                'city' => $b->city,
                'area' => $b->area,
                'latitude' => (float) $b->latitude,
                'longitude' => (float) $b->longitude,
                'business_phone' => $b->business_phone,
                'is_active' => (bool) $b->is_active,
            ])),

            'logo_image' => $this->whenLoaded('logoImage', fn () => $this->logoImage ? R2Storage::urlFromValue($this->logoImage->image_url) : null),
            'cover_image' => $this->whenLoaded('coverImage', fn () => $this->coverImage ? R2Storage::urlFromValue($this->coverImage->image_url) : null),

            'approval_status' => $this->approval_status,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'pending_changes' => $this->pending_changes,
            'has_pending_changes' => (bool) $this->has_pending_changes,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AttractionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'address' => $this->address,
            'city' => $this->city,
            'area' => $this->area,
            'district' => $this->district,
            'country' => $this->country,
            'is_free' => (bool) $this->is_free,
            'entry_fee' => $this->entry_fee !== null ? (float) $this->entry_fee : null,
            'currency' => $this->currency,
            'opening_hours' => $this->opening_hours,
            'contact_info' => $this->contact_info,
            'facilities' => $this->facilities,
            'best_time_to_visit' => $this->best_time_to_visit,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'difficulty_level' => $this->difficulty_level,
            'accessibility_info' => $this->accessibility_info,
            'overall_rating' => (float) $this->overall_rating,
            'total_reviews' => $this->total_reviews,
            'total_likes' => $this->total_likes,
            'total_dislikes' => $this->total_dislikes,
            'total_shares' => $this->total_shares,
            'total_views' => $this->total_views,
            'discovery_score' => (float) $this->discovery_score,
            'is_verified' => (bool) $this->is_verified,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'cover_image_url' => $this->cover_image_url,
            'gallery_count' => $this->gallery_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

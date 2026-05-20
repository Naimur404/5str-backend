<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferingSubmissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'business_id' => $this->business_id,
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->id,
                'business_name' => $this->business->business_name,
            ] : null),
            'business_name' => $this->business_name,
            'business_address' => $this->business_address,
            'offering_name' => $this->offering_name,
            'offering_description' => $this->offering_description,
            'offering_category' => $this->offering_category,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_type' => $this->price_type,
            'availability' => $this->availability,
            'contact_info' => $this->contact_info,
            'images' => $this->mapImages($this->images),
            'additional_info' => $this->additional_info,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function mapImages($value): array
    {
        $raw = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($raw)) {
            return [];
        }
        return array_values(array_map(fn ($v) => R2Storage::urlFromValue($v), $raw));
    }
}

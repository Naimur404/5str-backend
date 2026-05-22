<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'reviewable_type' => $this->reviewable_type,
            'reviewable_id' => $this->reviewable_id,
            'reviewable' => $this->whenLoaded('reviewable', function () {
                $r = $this->reviewable;
                if (! $r) {
                    return null;
                }
                return [
                    'id' => $r->id,
                    'name' => $r->business_name ?? $r->name ?? null,
                ];
            }),
            'overall_rating' => $this->overall_rating,
            'ratings' => $this->ratings,
            'title' => $this->title,
            'is_recommended' => (bool) $this->is_recommended,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'status' => $this->status,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

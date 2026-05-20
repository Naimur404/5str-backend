<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AttractionReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'attraction_id' => $this->attraction_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->is_anonymous ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'rating' => (float) $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'visit_info' => $this->visit_info,
            'experience_tags' => $this->experience_tags,
            'is_verified' => (bool) $this->is_verified,
            'is_featured' => (bool) $this->is_featured,
            'is_anonymous' => (bool) $this->is_anonymous,
            'helpful_votes' => $this->helpful_votes,
            'total_votes' => $this->total_votes,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'visit_date' => $this->visit_date,
            'meta_data' => $this->meta_data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class UserAttractionInteractionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'attraction_id' => $this->attraction_id,
            'attraction' => $this->whenLoaded('attraction', fn () => [
                'id' => $this->attraction->id,
                'name' => $this->attraction->name,
            ]),
            'interaction_type' => $this->interaction_type,
            'interaction_data' => $this->interaction_data,
            'notes' => $this->notes,
            'visit_info' => $this->visit_info,
            'is_public' => (bool) $this->is_public,
            'user_rating' => $this->user_rating !== null ? (float) $this->user_rating : null,
            'is_active' => (bool) $this->is_active,
            'interaction_date' => $this->interaction_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

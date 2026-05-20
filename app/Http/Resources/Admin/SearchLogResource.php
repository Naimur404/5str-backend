<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SearchLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'search_term' => $this->search_term,
            'category_id' => $this->category_id,
            'user_latitude' => $this->user_latitude !== null ? (float) $this->user_latitude : null,
            'user_longitude' => $this->user_longitude !== null ? (float) $this->user_longitude : null,
            'user_area' => $this->user_area,
            'filters_applied' => $this->filters_applied,
            'results_count' => $this->results_count,
            'clicked_business_id' => $this->clicked_business_id,
            'created_at' => $this->created_at,
        ];
    }
}

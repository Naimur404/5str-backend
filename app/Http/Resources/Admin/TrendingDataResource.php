<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class TrendingDataResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'location_area' => $this->location_area,
            'trend_score' => (float) $this->trend_score,
            'hybrid_score' => $this->hybrid_score !== null ? (float) $this->hybrid_score : null,
            'search_count' => $this->search_count,
            'view_count' => $this->view_count,
            'time_period' => $this->time_period,
            'date_period' => $this->date_period,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

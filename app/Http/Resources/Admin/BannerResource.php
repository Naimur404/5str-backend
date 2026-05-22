<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => R2Storage::urlFromValue($this->image_url),
            'link_type' => $this->link_type,
            'link_id' => $this->link_id,
            'link_url' => $this->link_url,
            'position' => $this->position,
            'target_location' => $this->target_location,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'click_count' => $this->click_count,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

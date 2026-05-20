<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),
            'level' => $this->level,
            'description' => $this->description,
            'icon_image' => R2Storage::urlFromValue($this->icon_image),
            'banner_image' => R2Storage::urlFromValue($this->banner_image),
            'color_code' => $this->color_code,
            'rating_criteria' => $this->rating_criteria,
            'sort_order' => $this->sort_order,
            'is_featured' => (bool) $this->is_featured,
            'is_popular' => (bool) $this->is_popular,
            'is_active' => (bool) $this->is_active,
            'total_businesses' => $this->total_businesses,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

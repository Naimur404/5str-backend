<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class AttractionGalleryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'attraction_id' => $this->attraction_id,
            'image_url' => R2Storage::urlFromValue($this->image_url),
            'image_path' => $this->image_path,
            'title' => $this->title,
            'description' => $this->description,
            'alt_text' => $this->alt_text,
            'is_cover' => (bool) $this->is_cover,
            'sort_order' => $this->sort_order,
            'image_type' => $this->image_type,
            'meta_data' => $this->meta_data,
            'uploaded_by' => $this->uploaded_by,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources\Admin;

use App\Support\R2Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class AttractionSubmissionResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'address' => $this->address,
            'city' => $this->city,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'entry_fee' => $this->entry_fee !== null ? (float) $this->entry_fee : null,
            'visiting_hours' => is_string($this->visiting_hours) ? json_decode($this->visiting_hours, true) : $this->visiting_hours,
            'best_time_to_visit' => $this->best_time_to_visit,
            'facilities' => is_string($this->facilities) ? json_decode($this->facilities, true) : $this->facilities,
            'images' => $this->mapImages($this->images),
            'additional_info' => $this->additional_info,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'approved_attraction_id' => $this->approved_attraction_id,
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

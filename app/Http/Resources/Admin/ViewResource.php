<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ViewResource extends JsonResource
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
            'viewable_type' => $this->viewable_type,
            'viewable_id' => $this->viewable_id,
            'user_latitude' => $this->user_latitude !== null ? (float) $this->user_latitude : null,
            'user_longitude' => $this->user_longitude !== null ? (float) $this->user_longitude : null,
            'user_area' => $this->user_area,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'session_id' => $this->session_id,
            'created_at' => $this->created_at,
        ];
    }
}

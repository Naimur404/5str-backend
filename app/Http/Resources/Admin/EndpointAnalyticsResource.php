<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class EndpointAnalyticsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'endpoint' => $this->endpoint,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'user_area' => $this->user_area,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'additional_data' => $this->additional_data,
            'created_at' => $this->created_at,
        ];
    }
}

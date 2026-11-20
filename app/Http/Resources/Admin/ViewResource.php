<?php

namespace App\Http\Resources\Admin;

use App\Models\Attraction;
use App\Models\Business;
use App\Models\BusinessOffering;
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
            // Resolved target name + optional parent context (e.g. an
            // offering carries its business name) so the admin views
            // table can show "Pizza Margherita — Pino's" instead of
            // raw "App\Models\BusinessOffering · #42".
            'target' => $this->resolveTarget(),
            'user_latitude' => $this->user_latitude !== null ? (float) $this->user_latitude : null,
            'user_longitude' => $this->user_longitude !== null ? (float) $this->user_longitude : null,
            'user_area' => $this->user_area,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'session_id' => $this->session_id,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{type: string, name: string|null, parent: string|null}|null
     */
    private function resolveTarget(): ?array
    {
        $target = $this->whenLoaded('viewable', fn () => $this->viewable, fn () => null);
        if (! $target) {
            return [
                'type' => $this->shortType($this->viewable_type),
                'name' => null,
                'parent' => null,
            ];
        }

        return match (true) {
            $target instanceof Business => [
                'type' => 'business',
                'name' => $target->business_name,
                'parent' => null,
            ],
            $target instanceof BusinessOffering => [
                'type' => 'offering',
                'name' => $target->name,
                'parent' => $target->relationLoaded('business') && $target->business
                    ? $target->business->business_name
                    : null,
            ],
            $target instanceof Attraction => [
                'type' => 'attraction',
                'name' => $target->name,
                'parent' => null,
            ],
            default => [
                'type' => $this->shortType($this->viewable_type),
                'name' => null,
                'parent' => null,
            ],
        };
    }

    private function shortType(?string $type): string
    {
        if (! $type) return 'unknown';
        $parts = explode('\\', $type);
        return strtolower(end($parts));
    }
}

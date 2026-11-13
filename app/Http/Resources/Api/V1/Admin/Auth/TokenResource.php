<?php

namespace App\Http\Resources\Api\V1\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'abilities' => $this->abilities,
            'is_current' => $this->id === $request->user()->currentAccessToken()->getKey(),
            'created_at' => $this->created_at?->toISOString(),
            'last_used_at' => $this->last_used_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}

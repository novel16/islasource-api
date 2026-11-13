<?php

namespace App\Http\Resources\Api\V1\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->accessToken->tokenable),
            'access_token' => $this->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->accessToken->expires_at?->toISOString(),
        ];
    }
}

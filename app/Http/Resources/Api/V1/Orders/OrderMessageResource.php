<?php

namespace App\Http\Resources\Api\V1\Orders;

use App\Models\OrderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderMessage */
class OrderMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->sender_name,
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

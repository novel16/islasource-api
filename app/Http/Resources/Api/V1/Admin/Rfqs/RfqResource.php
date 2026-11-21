<?php

namespace App\Http\Resources\Api\V1\Admin\Rfqs;

use App\Models\Rfq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rfq */
class RfqResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'reference' => $this->reference,
            'customer_name' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->customer_phone,
            'location' => $this->delivery_location,
            'requested_delivery_date' => $this->requested_delivery_date?->toDateString(),
            'notes' => $this->customer_notes ?? '',
            'status' => $this->status,
            'items' => $this->whenLoaded('items', fn (): AnonymousResourceCollection => RfqItemResource::collection($this->items)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

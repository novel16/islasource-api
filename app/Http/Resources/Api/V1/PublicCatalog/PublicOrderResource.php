<?php

namespace App\Http\Resources\Api\V1\PublicCatalog;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class PublicOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $availableItems = $this->quotation->items->where('is_available', true)->values();

        return [
            'reference' => $this->reference,
            'quotation_reference' => $this->quotation->reference,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => 'QR Ph',
            'customer_name' => $this->customer_name,
            'delivery_location' => $this->delivery_location,
            'requested_delivery_date' => $this->requested_delivery_date?->toDateString(),
            'items' => PublicQuotationItemResource::collection($availableItems),
            'subtotal_centavos' => (int) $this->subtotal_centavos,
            'delivery_centavos' => (int) $this->delivery_centavos,
            'total_centavos' => (int) $this->total_centavos,
            'paid_centavos' => (int) $this->paid_centavos,
            'paid_at' => $this->paid_at?->toISOString(),
            'packed_at' => $this->packed_at?->toISOString(),
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

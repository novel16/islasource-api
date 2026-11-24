<?php

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Http\Resources\Api\V1\PublicCatalog\PublicQuotationItemResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('quotation') && $this->quotation->relationLoaded('items')
            ? $this->quotation->items->where('is_available', true)->values()
            : null;

        return [
            'id' => (int) $this->id,
            'quotation_id' => (int) $this->quotation_id,
            'rfq_id' => (int) $this->rfq_id,
            'reference' => $this->reference,
            'quotation_reference' => $this->whenLoaded('quotation', fn (): string => $this->quotation->reference),
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'customer_name' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->customer_phone,
            'location' => $this->delivery_location,
            'requested_delivery_date' => $this->requested_delivery_date?->toDateString(),
            'subtotal_centavos' => (int) $this->subtotal_centavos,
            'delivery_centavos' => (int) $this->delivery_centavos,
            'total_centavos' => (int) $this->total_centavos,
            'paid_centavos' => (int) $this->paid_centavos,
            'tracking_url' => app(OrderService::class)->trackingUrl($this->resource) ?? '',
            'paid_at' => $this->paid_at?->toISOString(),
            'packed_at' => $this->packed_at?->toISOString(),
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason ?? '',
            'items' => $this->when(
                $items !== null,
                fn (): AnonymousResourceCollection => PublicQuotationItemResource::collection($items),
            ),
            'payments' => $this->whenLoaded(
                'payments',
                fn (): AnonymousResourceCollection => OrderPaymentResource::collection($this->payments),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

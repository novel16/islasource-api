<?php

namespace App\Http\Resources\Api\V1\PublicCatalog;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quotation */
class PublicQuotationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'rfq_reference' => $this->rfq->reference,
            'version' => (int) $this->version,
            'status' => $this->status,
            'customer_name' => $this->rfq->customer_name,
            'delivery_location' => $this->rfq->delivery_location,
            'requested_delivery_date' => $this->rfq->requested_delivery_date?->toDateString(),
            'items' => PublicQuotationItemResource::collection($this->items),
            'subtotal_centavos' => (int) $this->subtotal_centavos,
            'delivery_centavos' => (int) $this->delivery_centavos,
            'total_centavos' => (int) $this->total_centavos,
            'deposit_percent' => (int) $this->deposit_percent,
            'deposit_centavos' => (int) $this->deposit_centavos,
            'balance_centavos' => (int) $this->balance_centavos,
            'validity_hours' => (int) $this->validity_hours,
            'valid_until' => $this->valid_until?->toISOString(),
            'terms' => $this->terms ?? '',
            'sent_at' => $this->sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'declined_at' => $this->declined_at?->toISOString(),
            'decline_reason' => $this->decline_reason ?? '',
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
    }
}

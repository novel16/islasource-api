<?php

namespace App\Http\Resources\Api\V1\PublicCatalog;

use App\Models\Rfq;
use App\Models\RfqItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rfq */
class PublicRfqScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'delivery_location' => $this->delivery_location,
            'requested_delivery_date' => $this->requested_delivery_date?->toDateString(),
            'proposed_available_date' => $this->proposed_available_date?->toDateString(),
            'proposed_delivery_date' => $this->proposed_delivery_date?->toDateString(),
            'schedule_notice' => $this->schedule_notice ?? '',
            'schedule_status' => $this->schedule_status,
            'schedule_responded_at' => $this->schedule_responded_at?->toISOString(),
            'schedule_customer_note' => $this->schedule_customer_note ?? '',
            'items' => $this->items->map(fn (RfqItem $item): array => [
                'id' => (int) $item->id,
                'name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => (string) $item->quantity,
                'notes' => $item->customer_notes ?? '',
            ])->values()->all(),
        ];
    }
}

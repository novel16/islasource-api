<?php

namespace App\Http\Resources\Api\V1\Admin\Rfqs;

use App\Http\Resources\Api\V1\Admin\Quotations\QuotationSummaryResource;
use App\Models\Rfq;
use App\Services\RfqService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rfq */
class RfqResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?bool $emailSent = null)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDetail = $this->relationLoaded('notes');
        $scheduleUrl = $isDetail ? app(RfqService::class)->scheduleShareUrl($this->resource) : null;

        return [
            'id' => (int) $this->id,
            'reference' => $this->reference,
            'customer_name' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->customer_phone,
            'location' => $this->delivery_location,
            'requested_delivery_date' => $this->requested_delivery_date?->toDateString(),
            'proposed_available_date' => $this->proposed_available_date?->toDateString(),
            'proposed_delivery_date' => $this->proposed_delivery_date?->toDateString(),
            'notes' => $this->customer_notes ?? '',
            'schedule_notice' => $this->schedule_notice ?? '',
            'status' => $this->status,
            'schedule_status' => $this->schedule_status,
            'schedule_responded_at' => $this->schedule_responded_at?->toISOString(),
            'schedule_customer_note' => $this->schedule_customer_note ?? '',
            'schedule_url' => $this->when($isDetail, $scheduleUrl ?? ''),
            'email_sent' => $this->when($this->emailSent !== null, $this->emailSent),
            'items' => $this->whenLoaded('items', fn (): AnonymousResourceCollection => RfqItemResource::collection($this->items)),
            'internal_notes' => $this->whenLoaded('notes', fn (): AnonymousResourceCollection => RfqNoteResource::collection($this->notes)),
            'quotations' => $this->whenLoaded('quotations', fn (): AnonymousResourceCollection => QuotationSummaryResource::collection($this->quotations)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

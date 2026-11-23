<?php

namespace App\Http\Resources\Api\V1\Admin\Quotations;

use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quotation */
class QuotationResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?bool $emailSent = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isDetail = $this->relationLoaded('items');
        $shareUrl = $isDetail ? app(QuotationService::class)->shareUrl($this->resource) : null;

        return [
            'id' => (int) $this->id,
            'rfq_id' => (int) $this->rfq_id,
            'reference' => $this->reference,
            'version' => (int) $this->version,
            'status' => $this->status,
            'seafood_cost_centavos' => (int) $this->seafood_cost_centavos,
            'handling_centavos' => (int) $this->handling_centavos,
            'markup_percent' => (string) $this->markup_percent,
            'markup_centavos' => (int) $this->markup_centavos,
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
            'cancel_reason' => $this->cancel_reason ?? '',
            'last_emailed_at' => $this->last_emailed_at?->toISOString(),
            'last_email_error' => $this->last_email_error ?? '',
            'item_count' => $this->whenCounted('items'),
            'share_url' => $this->when($isDetail, $shareUrl ?? ''),
            'email_sent' => $this->when($this->emailSent !== null, $this->emailSent),
            'rfq' => $this->whenLoaded('rfq', fn (): array => [
                'id' => (int) $this->rfq->id,
                'reference' => $this->rfq->reference,
                'status' => $this->rfq->status,
                'customer_name' => $this->rfq->customer_name,
                'email' => $this->rfq->customer_email,
                'phone' => $this->rfq->customer_phone,
                'location' => $this->rfq->delivery_location,
                'requested_delivery_date' => $this->rfq->requested_delivery_date?->toDateString(),
            ]),
            'items' => $this->whenLoaded('items', fn (): AnonymousResourceCollection => QuotationItemResource::collection($this->items)),
            'versions' => $this->when(
                $this->relationLoaded('rfq') && $this->rfq->relationLoaded('quotations'),
                fn (): AnonymousResourceCollection => QuotationSummaryResource::collection($this->rfq->quotations),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Admin\Quotations;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quotation */
class QuotationSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'reference' => $this->reference,
            'version' => (int) $this->version,
            'status' => $this->status,
            'total_centavos' => (int) $this->total_centavos,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

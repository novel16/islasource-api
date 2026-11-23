<?php

namespace App\Http\Resources\Api\V1\PublicCatalog;

use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuotationItem */
class PublicQuotationItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'product_name' => $this->product_name,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'specs' => $this->specs ?? '',
            'is_available' => $this->is_available,
            'line_selling_centavos' => (int) $this->line_selling_centavos,
        ];
    }
}

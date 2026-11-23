<?php

namespace App\Http\Resources\Api\V1\Admin\Quotations;

use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuotationItem */
class QuotationItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'rfq_item_id' => $this->rfq_item_id === null ? null : (int) $this->rfq_item_id,
            'product_name' => $this->product_name,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'specs' => $this->specs ?? '',
            'is_available' => $this->is_available,
            'unit_cost_centavos' => (int) $this->unit_cost_centavos,
            'line_cost_centavos' => (int) $this->line_cost_centavos,
            'line_selling_centavos' => (int) $this->line_selling_centavos,
        ];
    }
}

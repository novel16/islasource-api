<?php

namespace App\Http\Resources\Api\V1\Admin\Rfqs;

use App\Models\RfqItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RfqItem */
class RfqItemResource extends JsonResource
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
            'product_id' => $this->product_id === null ? null : (int) $this->product_id,
            'name' => $this->product_name,
            'slug' => $this->product_slug ?? '',
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'notes' => $this->customer_notes ?? '',
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Admin\Rfqs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RfqCollection extends ResourceCollection
{
    public $collects = RfqResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}

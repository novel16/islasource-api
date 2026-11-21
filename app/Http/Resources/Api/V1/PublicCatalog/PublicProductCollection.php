<?php

namespace App\Http\Resources\Api\V1\PublicCatalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PublicProductCollection extends ResourceCollection
{
    public $collects = PublicProductResource::class;

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

<?php

namespace App\Http\Resources\Api\V1\PublicCatalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class PublicProductResource extends JsonResource
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
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'description' => $this->description,
            'specs' => $this->specs ?? '',
            'slug' => $this->slug,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'og_title' => $this->og_title ?? '',
            'og_description' => $this->og_description ?? '',
            'image' => $this->image,
            'image_alt' => $this->image_alt,
            'gallery_images' => $this->whenLoaded('galleryImages', fn (): array => $this->galleryImages
                ->map(fn (ProductImage $img): array => [
                    'image' => $img->image,
                    'image_alt' => $img->image_alt ?? $this->name,
                ])->values()->all()),
            'og_image' => $this->og_image ?? '',
            'canonical_url' => $this->canonical_url ?? '',
            'indexable' => $this->indexable,
            'min_order_quantity' => $this->min_order_quantity,
            'availability_note' => $this->availability_note ?? '',
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

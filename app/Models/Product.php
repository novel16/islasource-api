<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'category', 'unit', 'status', 'description', 'specs', 'slug',
    'meta_title', 'meta_description', 'og_title', 'og_description', 'image',
    'image_alt', 'og_image', 'canonical_url', 'indexable', 'min_order_quantity', 'availability_note',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const array CATEGORIES = ['Dried seafood', 'Fresh seafood', 'Frozen seafood'];

    public const array UNITS = ['kg', 'pack', 'piece'];

    public const array STATUSES = ['Draft', 'Published', 'Hidden'];

    public const int MAX_GALLERY_IMAGES = 7;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['indexable' => 'boolean', 'min_order_quantity' => 'decimal:3'];
    }

    /** @return HasMany<ProductSlug, $this> */
    public function slugs(): HasMany
    {
        return $this->hasMany(ProductSlug::class)->orderBy('id');
    }

    /** @return HasMany<ProductImage, $this> */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }
}

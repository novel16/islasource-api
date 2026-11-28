<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductSlug;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query();

        if ($filters['archived'] ?? false) {
            $query->onlyTrashed();
        }

        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('slug', '%'.$search.'%')
                    ->orWhereLike('category', '%'.$search.'%');
            });
        }

        foreach (['category', 'status'] as $filter) {
            if ($filters[$filter] ?? null) {
                $query->where($filter, $filters[$filter]);
            }
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 15)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null));
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product
    {
        return $this->withSlugTransaction(function () use ($attributes): Product {
            $hasGallery = array_key_exists('gallery_images', $attributes);
            $gallery = $hasGallery && is_array($attributes['gallery_images']) ? $attributes['gallery_images'] : [];
            unset($attributes['gallery_images']);

            $product = Product::query()->create($this->normalize($attributes));
            $this->claimSlug($product, $product->slug);

            if ($hasGallery) {
                $this->syncGallery($product, $gallery);
            }

            return $product->refresh()->load(['slugs', 'galleryImages']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product
    {
        return $this->withSlugTransaction(function () use ($product, $attributes): Product {
            $hasGallery = array_key_exists('gallery_images', $attributes);
            $gallery = $hasGallery && is_array($attributes['gallery_images']) ? $attributes['gallery_images'] : [];
            unset($attributes['gallery_images']);

            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->claimSlug($product, $attributes['slug'] ?? $product->slug);
            $product->fill($this->normalize($attributes, $product))->save();

            if ($hasGallery) {
                $this->syncGallery($product, $gallery);
            }

            return $product->refresh()->load(['slugs', 'galleryImages']);
        });
    }

    public function archive(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            Product::query()->lockForUpdate()->findOrFail($product->id)->delete();
        });
    }

    public function restore(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $product = Product::onlyTrashed()->lockForUpdate()->findOrFail($product->id);
            $product->restore();

            return $product->refresh()->load(['slugs', 'galleryImages']);
        });
    }

    /** @param Closure(): Product $operation */
    private function withSlugTransaction(Closure $operation): Product
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'The slug has already been taken.']);
        }
    }

    private function claimSlug(Product $product, string $slug): void
    {
        $claim = ProductSlug::query()->firstOrCreate(['slug' => $slug], ['product_id' => $product->id]);

        if ((int) $claim->product_id !== (int) $product->id) {
            throw ValidationException::withMessages(['slug' => 'The slug has already been taken.']);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes, ?Product $product = null): array
    {
        if ($product === null || array_key_exists('image_alt', $attributes)) {
            $alt = $attributes['image_alt'] ?? null;
            $attributes['image_alt'] = $alt === null || $alt === '' ? ($attributes['name'] ?? $product?->name) : $alt;
        }

        if (array_key_exists('image', $attributes) && ! $attributes['image']) {
            $attributes['image'] = '/images/squid.jpg';
        }

        return $attributes;
    }

    /** @param array<int, array<string, mixed>> $gallery */
    private function syncGallery(Product $product, array $gallery): void
    {
        $product->galleryImages()->delete();

        foreach (array_values(array_slice($gallery, 0, Product::MAX_GALLERY_IMAGES)) as $index => $entry) {
            $alt = isset($entry['image_alt']) && is_string($entry['image_alt'])
                ? trim($entry['image_alt'])
                : '';

            $product->galleryImages()->create([
                'image' => trim((string) $entry['image']),
                'image_alt' => $alt !== '' ? $alt : $product->name,
                'sort_order' => $index,
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PublicCatalogService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginatePublished(array $filters): LengthAwarePaginator
    {
        $query = Product::query()->where('status', 'Published');
        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('slug', '%'.$search.'%')
                    ->orWhereLike('category', '%'.$search.'%');
            });
        }

        if ($filters['category'] ?? null) {
            $query->where('category', $filters['category']);
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 100)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null));
    }

    public function findPublishedBySlug(string $slug): Product
    {
        $product = Product::query()
            ->where('status', 'Published')
            ->where(function (Builder $query) use ($slug): void {
                $query->where('slug', $slug)
                    ->orWhereHas('slugs', fn (Builder $slugs): Builder => $slugs->where('slug', $slug));
            })
            ->with('slugs')
            ->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        return $product;
    }
}

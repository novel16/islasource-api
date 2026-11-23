<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RfqService
{
    /** @param array<string, mixed> $attributes */
    public function submit(array $attributes): Rfq
    {
        return DB::transaction(function () use ($attributes): Rfq {
            $items = $attributes['items'];
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
            $products = Product::query()
                ->where('status', 'Published')
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $reference = 'RFQ-'.now()->format('Y').'-'.Str::upper(Str::random(8));

            $rfq = Rfq::query()->create([
                'reference' => $reference,
                'customer_name' => $attributes['customer_name'],
                'customer_email' => $attributes['email'],
                'customer_phone' => $attributes['phone'],
                'delivery_location' => $attributes['location'],
                'requested_delivery_date' => $attributes['requested_delivery_date'] ?? null,
                'customer_notes' => $attributes['notes'] ?? null,
                'status' => 'New',
            ]);

            foreach ($items as $index => $item) {
                $product = $item['product_id'] === null ? null : $products->get($item['product_id']);

                if ($item['product_id'] !== null && $product === null) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'This product is no longer available for quote requests.',
                    ]);
                }

                $requestedMilliunits = (int) round((float) $item['quantity'] * 1000);
                $minimumMilliunits = (int) round((float) $product?->min_order_quantity * 1000);

                if ($product !== null && $requestedMilliunits < $minimumMilliunits) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => "The minimum request for {$product->name} is {$product->min_order_quantity} {$product->unit}.",
                    ]);
                }

                $rfq->items()->create([
                    'product_id' => $product?->id,
                    'product_name' => $product?->name ?? $item['name'],
                    'product_slug' => $product?->slug,
                    'unit' => $product?->unit ?? $item['unit'],
                    'quantity' => $item['quantity'],
                    'customer_notes' => $item['notes'] ?? null,
                ]);
            }

            return $rfq;
        });
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Rfq>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Rfq::query()->with('items');

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('reference', '%'.$search.'%')
                    ->orWhereLike('customer_name', '%'.$search.'%')
                    ->orWhereLike('customer_email', '%'.$search.'%')
                    ->orWhereHas('items', fn (Builder $items): Builder => $items->whereLike('product_name', '%'.$search.'%'));
            });
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 15)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null));
    }

    public function findOrFail(int $id): Rfq
    {
        return Rfq::query()->with(['items', 'notes.author'])->findOrFail($id);
    }

    public function updateStatus(Rfq $rfq, string $status): Rfq
    {
        return DB::transaction(function () use ($rfq, $status): Rfq {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);
            $locked->status = $status;
            $locked->save();

            return $locked->refresh()->load(['items', 'notes.author']);
        });
    }

    public function addNote(Rfq $rfq, User $author, string $body): RfqNote
    {
        return DB::transaction(function () use ($rfq, $author, $body): RfqNote {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);
            $note = $locked->notes()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            return $note->load('author');
        });
    }
}

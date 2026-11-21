<?php

namespace App\Models;

use Database\Factories\RfqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rfq_id', 'product_id', 'product_name', 'product_slug', 'unit', 'quantity', 'customer_notes',
])]
class RfqItem extends Model
{
    /** @use HasFactory<RfqItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<Rfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

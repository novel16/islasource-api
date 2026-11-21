<?php

namespace App\Models;

use Database\Factories\RfqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'customer_name', 'customer_email', 'customer_phone',
    'delivery_location', 'requested_delivery_date', 'customer_notes', 'status',
])]
class Rfq extends Model
{
    /** @use HasFactory<RfqFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['requested_delivery_date' => 'date:Y-m-d'];
    }

    /** @return HasMany<RfqItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }
}

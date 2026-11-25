<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quotation_id', 'rfq_id', 'reference', 'status', 'payment_status',
    'customer_name', 'customer_email', 'customer_phone', 'delivery_location',
    'requested_delivery_date', 'confirmed_delivery_date',
    'subtotal_centavos', 'delivery_centavos', 'total_centavos', 'paid_centavos',
    'access_token_hash', 'access_token_encrypted',
    'paid_at', 'packed_at', 'dispatched_at', 'completed_at', 'cancelled_at', 'cancel_reason',
])]
#[Hidden(['access_token_hash', 'access_token_encrypted'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const array STATUSES = [
        'Awaiting Payment',
        'Processing',
        'Packed',
        'Dispatched',
        'Completed',
        'Cancelled',
    ];

    public const array PAYMENT_STATUSES = [
        'Unpaid',
        'Paid',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_delivery_date' => 'date:Y-m-d',
            'confirmed_delivery_date' => 'date:Y-m-d',
            'subtotal_centavos' => 'integer',
            'delivery_centavos' => 'integer',
            'total_centavos' => 'integer',
            'paid_centavos' => 'integer',
            'paid_at' => 'datetime',
            'packed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'access_token_encrypted' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<Rfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /** @return HasMany<OrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderByDesc('id');
    }

    /** @return HasMany<OrderMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class)->orderBy('id');
    }
}

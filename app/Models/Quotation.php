<?php

namespace App\Models;

use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rfq_id', 'reference', 'version', 'status',
    'seafood_cost_centavos', 'handling_centavos', 'markup_percent', 'markup_centavos',
    'subtotal_centavos', 'delivery_centavos', 'total_centavos',
    'deposit_percent', 'deposit_centavos', 'balance_centavos',
    'validity_hours', 'valid_until', 'terms',
    'sent_at', 'accepted_at', 'declined_at', 'decline_reason',
    'cancelled_at', 'cancel_reason',
    'access_token_hash', 'access_token_encrypted',
    'last_emailed_at', 'last_email_error', 'created_by',
])]
#[Hidden(['access_token_hash', 'access_token_encrypted'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    public const array STATUSES = [
        'Draft',
        'Sent',
        'Accepted',
        'Declined',
        'Expired',
        'Superseded',
        'Cancelled',
    ];

    public const int DEFAULT_VALIDITY_HOURS = 48;

    public const int DEFAULT_DEPOSIT_PERCENT = 50;

    public const string DEFAULT_TERMS = '50% deposit confirms your order. Balance is due before dispatch. Availability is confirmed upon deposit.';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'seafood_cost_centavos' => 'integer',
            'handling_centavos' => 'integer',
            'markup_percent' => 'decimal:2',
            'markup_centavos' => 'integer',
            'subtotal_centavos' => 'integer',
            'delivery_centavos' => 'integer',
            'total_centavos' => 'integer',
            'deposit_percent' => 'integer',
            'deposit_centavos' => 'integer',
            'balance_centavos' => 'integer',
            'validity_hours' => 'integer',
            'valid_until' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_emailed_at' => 'datetime',
            'access_token_encrypted' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Rfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

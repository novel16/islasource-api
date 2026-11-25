<?php

namespace App\Models;

use Database\Factories\RfqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'customer_name', 'customer_email', 'customer_phone',
    'delivery_location', 'requested_delivery_date', 'proposed_available_date', 'proposed_delivery_date',
    'customer_notes', 'schedule_notice', 'status', 'schedule_status',
    'schedule_responded_at', 'schedule_customer_note',
    'schedule_token_hash', 'schedule_token_encrypted',
])]
#[Hidden(['schedule_token_hash', 'schedule_token_encrypted'])]
class Rfq extends Model
{
    /** @use HasFactory<RfqFactory> */
    use HasFactory;

    public const array STATUSES = ['New', 'Reviewing', 'Waiting', 'Ready', 'Quoted', 'Closed'];

    public const array SCHEDULE_STATUSES = ['Proposed', 'Accepted', 'Declined', 'ClosedUnavailable'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_delivery_date' => 'date:Y-m-d',
            'proposed_available_date' => 'date:Y-m-d',
            'proposed_delivery_date' => 'date:Y-m-d',
            'schedule_responded_at' => 'datetime',
            'schedule_token_encrypted' => 'encrypted',
        ];
    }

    /** @return HasMany<RfqItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    /** @return HasMany<RfqNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(RfqNote::class)->orderBy('id');
    }

    /** @return HasMany<Quotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderByDesc('version');
    }
}

IslaSource Seafoods - Bantayan Island, Cebu

Hi {{ $rfq->customer_name }},

Your seafood quotation {{ $quotation->reference }}@if($quotation->version > 1) (Version {{ $quotation->version }})@endif for request {{ $rfq->reference }} is ready.
Delivery destination: {{ $rfq->delivery_location }}

Quoted items:
@foreach($availableItems as $item)
- {{ $item->product_name }} ({{ (float) $item->quantity }} {{ $item->unit }}): {{ $formatPeso($item->line_selling_centavos) }}@if($item->specs) - {{ $item->specs }}@endif
@endforeach
@if($unavailableItems->isNotEmpty())

Currently unavailable (not charged):
@foreach($unavailableItems as $item)
- {{ $item->product_name }} ({{ (float) $item->quantity }} {{ $item->unit }})@if($item->specs) - {{ $item->specs }}@endif
@endforeach
@endif

Seafood subtotal: {{ $formatPeso($quotation->subtotal_centavos) }}
Delivery: {{ $formatPeso($quotation->delivery_centavos) }}
Total quotation: {{ $formatPeso($quotation->total_centavos) }}
Required deposit ({{ $quotation->deposit_percent }}%): {{ $formatPeso($quotation->deposit_centavos) }}
Remaining balance before dispatch: {{ $formatPeso($quotation->balance_centavos) }}
@if($validUntilManila)
Valid until: {{ $validUntilManila }}
@endif

Terms:
{{ $quotation->terms }}

Review and respond to your quotation here:
{{ $shareUrl }}

No payment is required until you review and accept your quotation.

IslaSource Seafoods - Bantayan Island, Cebu

Hi {{ $rfq->customer_name }},

Your seafood quotation {{ $quotation->reference }}@if($quotation->version > 1) (Version {{ $quotation->version }})@endif for request {{ $rfq->reference }} is ready.
Delivery destination: {{ $rfq->delivery_location }}
@if($quotation->confirmed_delivery_date)
Confirmed delivery date: {{ $quotation->confirmed_delivery_date->format('M j, Y') }}
@endif

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
Delivery: {{ $quotation->delivery_centavos > 0 ? $formatPeso($quotation->delivery_centavos) : 'Free' }}
Total quotation (Full payment via QR Ph): {{ $formatPeso($quotation->total_centavos) }}
@if($validUntilManila)
Valid until: {{ $validUntilManila }}
@endif

Terms:
{{ $quotation->terms }}

Review and respond to your quotation here:
{{ $shareUrl }}

No payment is required until you review and accept your quotation.

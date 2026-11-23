IslaSource Seafoods - Bantayan Island, Cebu

Hi {{ $rfq->customer_name }},

We have received your seafood quote request.

Request Reference: {{ $rfq->reference }}
@if($submittedAtManila)
Submitted: {{ $submittedAtManila }}
@endif
Delivery Location: {{ $rfq->delivery_location }}
@if($rfq->requested_delivery_date)
Preferred Delivery Date: {{ $rfq->requested_delivery_date->format('M j, Y') }}
@endif

Requested items:
@foreach($items as $item)
- {{ $item->product_name }}: {{ (float) $item->quantity }} {{ $item->unit }}@if($item->customer_notes) ({{ $item->customer_notes }})@endif
@endforeach
@if($rfq->customer_notes)

Your notes:
{{ $rfq->customer_notes }}
@endif

What happens next:
1. We check catch availability and current supplier rates on Bantayan Island.
2. Once confirmed, we will email your personal quotation with final prices, delivery fee, and terms.
3. No payment or commitment is required until you review and approve your quotation.

Please keep reference {{ $rfq->reference }} for your records.

IslaSource Seafoods — New Customer Quote Request

Reference: {{ $rfq->reference }}
@if($submittedAtManila)
Submitted: {{ $submittedAtManila }}
@endif

Customer: {{ $rfq->customer_name }}
Email: {{ $rfq->customer_email }}
Phone: {{ $rfq->customer_phone }}
Delivery Location: {{ $rfq->delivery_location }}
@if($rfq->requested_delivery_date)
Preferred Date: {{ $rfq->requested_delivery_date->format('M j, Y') }}
@endif
@if($rfq->customer_notes)
Customer Notes: {{ $rfq->customer_notes }}
@endif

Requested items ({{ $items->count() }}):
@foreach($items as $item)
- {{ $item->product_name }} ({{ $item->product_id === null ? 'Custom' : 'Catalog' }}): {{ (float) $item->quantity }} {{ $item->unit }}@if($item->customer_notes) — {{ $item->customer_notes }}@endif
@endforeach

Review in Admin:
{{ $adminUrl }}

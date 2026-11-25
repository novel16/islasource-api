IslaSource Admin — Schedule Response ({{ $rfq->reference }})

@if($rfq->schedule_status === 'Accepted')
{{ $rfq->customer_name }} confirmed they are willing to wait for supply on {{ $rfq->proposed_available_date?->format('M j, Y') }} (delivery {{ $rfq->proposed_delivery_date?->format('M j, Y') }}).
@else
{{ $rfq->customer_name }} cancelled request {{ $rfq->reference }} after the schedule change.
@endif

@if($rfq->schedule_customer_note)
Customer note: {{ $rfq->schedule_customer_note }}

@endif
Open in Admin: {{ $adminUrl }}

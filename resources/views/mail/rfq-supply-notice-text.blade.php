IslaSource Seafoods — Request {{ $rfq->reference }}

Hi {{ $rfq->customer_name }},

@if($mode === 'reschedule')
We checked with our Bantayan Island fishermen and suppliers, and supply was not available for your original target schedule{{ $rfq->requested_delivery_date ? ' ('.$rfq->requested_delivery_date->format('M j, Y').')' : '' }}.

Estimated catch availability: {{ $rfq->proposed_available_date?->format('M j, Y') }}
Proposed delivery date: {{ $rfq->proposed_delivery_date?->format('M j, Y') }}

@if($rfq->schedule_notice)
Note from IslaSource Seafoods:
{{ $rfq->schedule_notice }}

@endif
Let us know if you would like to wait for this schedule or cancel your request:
{{ $actionUrl }}
@else
Unfortunately, our local suppliers on Bantayan Island do not have confirmed supply available to fulfill request {{ $rfq->reference }} at this time, so we have closed this request with no obligation.

@if($rfq->schedule_notice)
Note from IslaSource Seafoods:
{{ $rfq->schedule_notice }}

@endif
Explore seafood or submit a new request anytime:
{{ $actionUrl }}
@endif

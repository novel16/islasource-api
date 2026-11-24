IslaSource Seafoods — Order Paid via QR Ph (Ready to Source)

Order: {{ $order->reference }}
Amount Paid: {{ $formatPeso($order->paid_centavos) }}
@if($paidAtManila)
Paid At: {{ $paidAtManila }}
@endif

Customer: {{ $order->customer_name }} ({{ $order->customer_email }} / {{ $order->customer_phone }})
Delivery Location: {{ $order->delivery_location }}

Items:
@foreach($items as $item)
- {{ $item->product_name }}: {{ (float) $item->quantity }} {{ $item->unit }}
@endforeach

Open in Admin:
{{ $adminUrl }}

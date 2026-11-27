IslaSource Seafoods — Payment Confirmed

Hi {{ $order->customer_name }},

We received your full payment of {{ $formatPeso($order->paid_centavos) }} via PayMongo QR Ph for order {{ $order->reference }}@if($paidAtManila) on {{ $paidAtManila }}@endif.

Delivery location: {{ $order->delivery_location }}

Items:
@foreach($items as $item)
- {{ $item->product_name }} ({{ (float) $item->quantity }} {{ $item->unit }}): {{ $formatPeso($item->line_selling_centavos) }}
@endforeach

Subtotal: {{ $formatPeso($order->subtotal_centavos) }}
Delivery: {{ $order->delivery_centavos > 0 ? $formatPeso($order->delivery_centavos) : 'Free' }}
Total Paid: {{ $formatPeso($order->paid_centavos) }}

Track your order here:
{{ $orderUrl }}

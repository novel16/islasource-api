{{ $forAdmin ? "New customer message on Order {$order->reference}" : "New message on your IslaSource Seafoods Order ({$order->reference})" }}

From: {{ $orderMessage->sender_name }}

{{ $orderMessage->body }}

View and reply:
{{ $replyUrl }}

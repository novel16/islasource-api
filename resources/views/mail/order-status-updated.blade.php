<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Update — {{ $order->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Seafoods · Order Update</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">Your Order Is Now {{ $order->status }}</h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Order {{ $order->reference }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">Hi {{ $order->customer_name }},</p>
                <p style="margin:0 0 20px;color:#4a5a52;">
                    @if($order->status === 'Packed')
                        Your seafood order has been sourced and packed with care on Bantayan Island and is getting ready for dispatch to <strong>{{ $order->delivery_location }}</strong>.
                    @elseif($order->status === 'Dispatched')
                        Your seafood order is on its way to <strong>{{ $order->delivery_location }}</strong>.
                    @elseif($order->status === 'Completed')
                        Your seafood order has been marked delivered and completed. Thank you for ordering from IslaSource Seafoods!
                    @else
                        Your order status has been updated to <strong>{{ $order->status }}</strong>.
                    @endif
                </p>
                <p style="margin:0 0 14px;text-align:center;">
                    <a href="{{ $orderUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        View Order Progress
                    </a>
                </p>
                <p style="margin:0;font-size:12px;color:#6c7b72;word-break:break-all;">
                    Order link: <a href="{{ $orderUrl }}" style="color:#1e4d40;">{{ $orderUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

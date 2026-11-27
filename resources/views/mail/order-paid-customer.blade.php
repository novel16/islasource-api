<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmed — {{ $order->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Seafoods · Payment Receipt</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">Your Payment Is Confirmed</h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Order {{ $order->reference }} · Paid via QR Ph</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">Hi {{ $order->customer_name }},</p>
                <p style="margin:0 0 18px;color:#4a5a52;">
                    Thank you! We received your full payment of <strong>{{ $formatPeso($order->paid_centavos) }}</strong> via PayMongo QR Ph
                    @if($paidAtManila) on {{ $paidAtManila }}@endif.
                    Our team will now begin sourcing and preparing your seafood order for delivery to <strong>{{ $order->delivery_location }}</strong>.
                </p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse;border:1px solid #e4e8de;margin-bottom:18px;font-size:14px;">
                    <thead>
                        <tr style="background:#f3f6f0;color:#3d5446;text-align:left;">
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;">Seafood</th>
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;">Qty</th>
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;"><strong>{{ $item->product_name }}</strong></td>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;white-space:nowrap;">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;text-align:right;white-space:nowrap;">{{ $formatPeso($item->line_selling_centavos) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="6" style="font-size:14px;margin-bottom:20px;">
                    <tr>
                        <td style="color:#55665d;">Seafood subtotal</td>
                        <td style="text-align:right;">{{ $formatPeso($order->subtotal_centavos) }}</td>
                    </tr>
                    <tr>
                        <td style="color:#55665d;">Delivery</td>
                        <td style="text-align:right;">{{ $order->delivery_centavos > 0 ? $formatPeso($order->delivery_centavos) : 'Free' }}</td>
                    </tr>
                    <tr>
                        <td style="padding-top:10px;border-top:1px solid #dfe5da;font-weight:700;font-size:16px;">Total Paid (QR Ph)</td>
                        <td style="padding-top:10px;border-top:1px solid #dfe5da;text-align:right;font-weight:700;font-size:16px;color:#1e4d40;">{{ $formatPeso($order->paid_centavos) }}</td>
                    </tr>
                </table>

                <p style="margin:0 0 14px;text-align:center;">
                    <a href="{{ $orderUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        Track Your Order
                    </a>
                </p>
                <p style="margin:0;font-size:12px;color:#6c7b72;word-break:break-all;">
                    Order link: <a href="{{ $orderUrl }}" style="color:#1e4d40;">{{ $orderUrl }}</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 28px;background:#f3f5f0;font-size:12px;color:#6b7970;">
                IslaSource Seafoods · Bantayan Island, Cebu. Keep this email for your payment receipt.
            </td>
        </tr>
    </table>
</body>
</html>

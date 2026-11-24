<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Paid — {{ $order->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Admin Notification</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">Full Payment Verified — Ready to Source</h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">{{ $order->reference }} · {{ $formatPeso($order->paid_centavos) }} via QR Ph</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 16px;color:#4a5a52;">
                    PayMongo confirmed full QR Ph payment for <strong>{{ $order->customer_name }}</strong>@if($paidAtManila) ({{ $paidAtManila }})@endif. This order is now marked <strong>Processing</strong>.
                </p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="6" style="font-size:13px;margin-bottom:18px;background:#f5f7f3;border-radius:8px;padding:12px 14px;">
                    <tr><td style="width:140px;color:#5d6f64;">Customer</td><td><strong>{{ $order->customer_name }}</strong></td></tr>
                    <tr><td style="color:#5d6f64;">Email</td><td>{{ $order->customer_email }}</td></tr>
                    <tr><td style="color:#5d6f64;">Phone</td><td>{{ $order->customer_phone }}</td></tr>
                    <tr><td style="color:#5d6f64;">Deliver to</td><td>{{ $order->delivery_location }}</td></tr>
                    <tr><td style="color:#5d6f64;">Amount paid</td><td><strong>{{ $formatPeso($order->paid_centavos) }}</strong></td></tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse;border:1px solid #e4e8de;margin-bottom:20px;font-size:14px;">
                    <thead>
                        <tr style="background:#f3f6f0;color:#3d5446;text-align:left;">
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;">Seafood</th>
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;text-align:right;">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;"><strong>{{ $item->product_name }}</strong></td>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;text-align:right;">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p style="margin:0;text-align:center;">
                    <a href="{{ $adminUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        Open Order in Admin
                    </a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

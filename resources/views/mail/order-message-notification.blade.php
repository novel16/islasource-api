<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Message — {{ $order->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">
                    {{ $forAdmin ? 'IslaSource Admin · Customer Message' : 'IslaSource Seafoods · Order Message' }}
                </div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">
                    {{ $forAdmin ? "New Message from {$order->customer_name}" : 'New Message Regarding Your Order' }}
                </h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Order {{ $order->reference }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">
                    {{ $forAdmin ? 'A customer posted a follow-up message on their order:' : "Hi {$order->customer_name}, our team sent you an update on your order:" }}
                </p>
                <div style="margin:0 0 20px;padding:16px 18px;background:#faf9f5;border:1px solid #e4e8de;border-left:3px solid #1e4d40;border-radius:6px;">
                    <div style="font-size:12px;color:#5f6f65;margin-bottom:6px;">
                        <strong>{{ $orderMessage->sender_name }}</strong>
                    </div>
                    <div style="font-size:14px;color:#1d2b24;white-space:pre-line;">{{ $orderMessage->body }}</div>
                </div>
                <p style="margin:0 0 14px;text-align:center;">
                    <a href="{{ $replyUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        View &amp; Reply on Order Page
                    </a>
                </p>
                <p style="margin:0;font-size:12px;color:#6c7b72;word-break:break-all;">
                    Direct link: <a href="{{ $replyUrl }}" style="color:#1e4d40;">{{ $replyUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

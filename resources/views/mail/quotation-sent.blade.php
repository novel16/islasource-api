<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your IslaSource Seafoods Quotation</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Seafoods · Bantayan Island, Cebu</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">Your Seafood Quotation Is Ready</h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">
                    Quotation {{ $quotation->reference }}@if($quotation->version > 1) · Version {{ $quotation->version }}@endif
                    &nbsp;|&nbsp; Request {{ $rfq->reference }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">Hi {{ $rfq->customer_name }},</p>
                <p style="margin:0 0 18px;color:#4a5a52;">
                    Thank you for your quote request. We have checked current island availability and prepared your personal quotation for delivery to <strong>{{ $rfq->delivery_location }}</strong>@if($quotation->confirmed_delivery_date) (target delivery: <strong>{{ $quotation->confirmed_delivery_date->format('M j, Y') }}</strong>)@endif.
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
                        @foreach($availableItems as $item)
                            <tr>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;">
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->specs)
                                        <div style="font-size:12px;color:#66756d;margin-top:2px;">{{ $item->specs }}</div>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;white-space:nowrap;">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;text-align:right;white-space:nowrap;">{{ $formatPeso($item->line_selling_centavos) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($unavailableItems->isNotEmpty())
                    <div style="margin-bottom:18px;padding:12px 14px;background:#faf5ee;border:1px solid #eadbc8;border-radius:8px;font-size:13px;color:#6c5339;">
                        <strong>Currently unavailable (not charged):</strong>
                        <ul style="margin:6px 0 0;padding-left:18px;">
                            @foreach($unavailableItems as $item)
                                <li>{{ $item->product_name }} ({{ (float) $item->quantity }} {{ $item->unit }})@if($item->specs) — {{ $item->specs }}@endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <table role="presentation" width="100%" cellspacing="0" cellpadding="6" style="font-size:14px;margin-bottom:20px;">
                    <tr>
                        <td style="color:#55665d;">Seafood subtotal</td>
                        <td style="text-align:right;">{{ $formatPeso($quotation->subtotal_centavos) }}</td>
                    </tr>
                    <tr>
                        <td style="color:#55665d;">Delivery</td>
                        <td style="text-align:right;">{{ $formatPeso($quotation->delivery_centavos) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-top:10px;border-top:1px solid #dfe5da;font-weight:700;font-size:16px;">Total quotation (Full payment via QR Ph)</td>
                        <td style="padding-top:10px;border-top:1px solid #dfe5da;text-align:right;font-weight:700;font-size:16px;color:#1e4d40;">{{ $formatPeso($quotation->total_centavos) }}</td>
                    </tr>
                </table>

                @if($validUntilManila)
                    <p style="margin:0 0 14px;font-size:13px;color:#4a5a52;">
                        <strong>Valid until:</strong> {{ $validUntilManila }}
                    </p>
                @endif

                <div style="margin:0 0 22px;padding:12px 14px;background:#f5f7f3;border-radius:8px;font-size:13px;color:#4a5a52;">
                    <strong>Terms:</strong><br>
                    {{ $quotation->terms }}
                </div>

                <p style="margin:0 0 16px;text-align:center;">
                    <a href="{{ $shareUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        Review &amp; Respond to Your Quotation
                    </a>
                </p>
                <p style="margin:0;font-size:12px;color:#6c7b72;word-break:break-all;">
                    If the button does not open, copy this link into your browser:<br>
                    <a href="{{ $shareUrl }}" style="color:#1e4d40;">{{ $shareUrl }}</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 28px;background:#f3f5f0;font-size:12px;color:#6b7970;">
                IslaSource Seafoods · Sourced on request from Bantayan Island, Cebu. No payment is required until you review and accept your quotation.
            </td>
        </tr>
    </table>
</body>
</html>

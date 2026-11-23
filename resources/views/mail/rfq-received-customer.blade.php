<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Request Received — {{ $rfq->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Seafoods · Bantayan Island, Cebu</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">We Received Your Quote Request</h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">
                    Reference: <strong>{{ $rfq->reference }}</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">Hi {{ $rfq->customer_name }},</p>
                <p style="margin:0 0 18px;color:#4a5a52;">
                    Thank you for reaching out to IslaSource Seafoods. We have received your quote request and will check current availability and pricing with our local island partners.
                </p>

                <div style="margin:0 0 20px;padding:16px 18px;background:#f3f6f0;border:1px solid #dfe5da;border-radius:8px;text-align:center;">
                    <div style="font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#5d6f64;">Your Request Reference Number</div>
                    <div style="margin-top:6px;font-size:22px;font-weight:700;letter-spacing:0.6px;color:#1e4d40;">{{ $rfq->reference }}</div>
                    @if($submittedAtManila)
                        <div style="margin-top:4px;font-size:12px;color:#6b7970;">Submitted {{ $submittedAtManila }}</div>
                    @endif
                </div>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse;border:1px solid #e4e8de;margin-bottom:18px;font-size:14px;">
                    <thead>
                        <tr style="background:#f3f6f0;color:#3d5446;text-align:left;">
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;">Requested Seafood</th>
                            <th style="padding:10px 12px;border-bottom:1px solid #e4e8de;text-align:right;">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;">
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->customer_notes)
                                        <div style="font-size:12px;color:#66756d;margin-top:2px;">{{ $item->customer_notes }}</div>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #edf0e8;text-align:right;white-space:nowrap;">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="6" style="font-size:13px;margin-bottom:18px;color:#4a5a52;">
                    <tr>
                        <td style="width:170px;color:#66756d;">Delivery location</td>
                        <td><strong>{{ $rfq->delivery_location }}</strong></td>
                    </tr>
                    @if($rfq->requested_delivery_date)
                        <tr>
                            <td style="color:#66756d;">Preferred delivery date</td>
                            <td><strong>{{ $rfq->requested_delivery_date->format('M j, Y') }}</strong></td>
                        </tr>
                    @endif
                    @if($rfq->customer_notes)
                        <tr>
                            <td style="color:#66756d;vertical-align:top;">Your notes</td>
                            <td>{{ $rfq->customer_notes }}</td>
                        </tr>
                    @endif
                </table>

                <div style="margin:0;padding:14px 16px;background:#faf8f3;border:1px solid #e9e4d7;border-radius:8px;font-size:13px;color:#4a5a52;">
                    <strong style="color:#1d2b24;">What happens next:</strong>
                    <ol style="margin:8px 0 0;padding-left:18px;">
                        <li>We check catch availability and current supplier rates on Bantayan Island.</li>
                        <li>Once confirmed, we will email your personal quotation with final prices, delivery fee, and terms.</li>
                        <li>You review and decide — no payment or commitment is required until you approve your quotation.</li>
                    </ol>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 28px;background:#f3f5f0;font-size:12px;color:#6b7970;">
                Please keep your reference number ({{ $rfq->reference }}) for your records. — IslaSource Seafoods, Bantayan Island, Cebu.
            </td>
        </tr>
    </table>
</body>
</html>

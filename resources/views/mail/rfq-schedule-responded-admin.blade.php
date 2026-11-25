<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Response — {{ $rfq->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Admin · Schedule Response</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">
                    {{ $rfq->schedule_status === 'Accepted' ? 'Customer Agreed to New Schedule' : 'Customer Cancelled Request' }}
                </h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">{{ $rfq->reference }} · {{ $rfq->customer_name }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;color:#4a5a52;">
                    @if($rfq->schedule_status === 'Accepted')
                        <strong>{{ $rfq->customer_name }}</strong> confirmed they are willing to wait for supply available on <strong>{{ $rfq->proposed_available_date?->format('M j, Y') }}</strong> (delivery on <strong>{{ $rfq->proposed_delivery_date?->format('M j, Y') }}</strong>). Prepare and send their quotation once catch is confirmed.
                    @else
                        <strong>{{ $rfq->customer_name }}</strong> declined the rescheduled dates, and request <strong>{{ $rfq->reference }}</strong> has been marked Closed automatically.
                    @endif
                </p>
                @if($rfq->schedule_customer_note)
                    <div style="margin:0 0 20px;padding:14px 18px;background:#faf9f5;border-left:3px solid #d88362;color:#2c3b33;font-size:14px;white-space:pre-line;">{{ $rfq->schedule_customer_note }}</div>
                @endif
                <p style="margin:0;text-align:center;">
                    <a href="{{ $adminUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        Open Request in Admin
                    </a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

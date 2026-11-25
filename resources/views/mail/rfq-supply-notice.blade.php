<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Update — {{ $rfq->reference }}</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Seafoods · Supply Update</div>
                <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;">
                    {{ $mode === 'reschedule' ? 'New Supply & Delivery Schedule Available' : 'Update on Your Seafood Request' }}
                </h1>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Request {{ $rfq->reference }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">Hi {{ $rfq->customer_name }},</p>
                @if($mode === 'reschedule')
                    <p style="margin:0 0 16px;color:#4a5a52;">
                        We checked directly with our local fishermen and suppliers on Bantayan Island, and fresh supply wasn’t available for your original target schedule{{ $rfq->requested_delivery_date ? ' ('.$rfq->requested_delivery_date->format('M j, Y').')' : '' }}. We can source your request on the updated schedule below if you’d like us to proceed:
                    </p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;background:#f4f7f2;border:1px solid #dce5d8;border-radius:8px;">
                        <tr>
                            <td style="padding:14px 18px;">
                                <div style="font-size:12px;color:#5f6f65;">Estimated catch availability: <strong style="color:#1d2b24;">{{ $rfq->proposed_available_date?->format('M j, Y') }}</strong></div>
                                <div style="font-size:12px;color:#5f6f65;margin-top:6px;">Proposed delivery date: <strong style="color:#1d2b24;">{{ $rfq->proposed_delivery_date?->format('M j, Y') }}</strong></div>
                            </td>
                        </tr>
                    </table>
                @else
                    <p style="margin:0 0 16px;color:#4a5a52;">
                        Thank you for requesting a seafood quote from IslaSource Seafoods. Unfortunately, our local suppliers on Bantayan Island do not have confirmed catch or supply available to fulfill your request at this time, so we have closed <strong>{{ $rfq->reference }}</strong> with no obligation on your part.
                    </p>
                @endif

                @if($rfq->schedule_notice)
                    <div style="margin:0 0 20px;padding:14px 18px;background:#faf9f5;border-left:3px solid #d88362;color:#2c3b33;font-size:14px;white-space:pre-line;">{{ $rfq->schedule_notice }}</div>
                @endif

                <p style="margin:0 0 14px;text-align:center;">
                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 24px;background:#1e4d40;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
                        {{ $mode === 'reschedule' ? 'Confirm New Schedule or Cancel' : 'Explore Available Seafood' }}
                    </a>
                </p>
                <p style="margin:0;font-size:12px;color:#6c7b72;word-break:break-all;">
                    Link: <a href="{{ $actionUrl }}" style="color:#1e4d40;">{{ $actionUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

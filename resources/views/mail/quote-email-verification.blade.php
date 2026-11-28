<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email — IslaSource Seafoods</title>
</head>
<body style="margin:0;padding:24px;background:#f7f5f0;font-family:Arial,Helvetica,sans-serif;color:#1d2b24;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e4e8de;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="padding:22px 28px;background:#1e4d40;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;opacity:0.85;">IslaSource Seafoods · Bantayan Island, Cebu</div>
                <h1 style="margin:8px 0 0;font-size:21px;font-weight:700;">Verify Your Email Address</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 14px;">Hi {{ $customerName }},</p>
                <p style="margin:0 0 18px;color:#4a5a52;">
                    Enter this 6-digit verification code on our quote request page so we know where to send your confirmed Bantayan Island seafood quotation:
                </p>
                <div style="margin:0 0 18px;padding:18px;background:#f3f6f0;border:1px solid #dfe5da;border-radius:10px;text-align:center;">
                    <div style="font-size:11px;letter-spacing:1.3px;text-transform:uppercase;color:#5d6f64;">Verification Code</div>
                    <div style="margin-top:6px;font-size:30px;font-weight:700;letter-spacing:6px;color:#1e4d40;">{{ $code }}</div>
                    <div style="margin-top:6px;font-size:12px;color:#6b7970;">Valid for {{ $validMinutes }} minutes · One-time use</div>
                </div>
                <p style="margin:0;font-size:13px;color:#66756d;">
                    If you didn’t request a seafood quotation from IslaSource Seafoods, you can safely ignore this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

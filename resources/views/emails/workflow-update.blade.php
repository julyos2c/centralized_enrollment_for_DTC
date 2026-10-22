<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $eventTitle }}</title>
</head>
<body style="margin:0;background:#f1f5f9;color:#1e293b;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 14px;background:#f1f5f9;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#ffffff;">
                    <tr>
                        <td style="padding:22px 26px;background:#0f4cdb;color:#ffffff;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">DTC Nexus</div>
                            <div style="margin-top:5px;font-size:21px;font-weight:700;line-height:1.3;">{{ $eventTitle }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $recipientName }},</p>
                            <p style="margin:0;color:#475569;font-size:14px;line-height:1.7;">{{ $eventMessage }}</p>
                            @if($actionUrl)
                                <p style="margin:24px 0 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:11px 18px;border-radius:9px;background:#0f4cdb;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;">{{ $actionLabel }}</a>
                                </p>
                            @endif
                            <p style="margin:24px 0 0;color:#94a3b8;font-size:12px;line-height:1.6;">This is a transactional update about your DTC Nexus record.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

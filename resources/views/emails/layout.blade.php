<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $heading }}</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;color:#334155;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:32px 16px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-top:3px solid #0f4cdb;border-radius:8px;">
        <tr><td style="padding:24px 24px 0;">
            <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                @if(!empty($emailMessage))
                <td valign="middle" style="width:40px;padding-right:12px;">
                    <img src="{{ $emailMessage->embed(public_path('images/DTC-LOGO.png')) }}" width="40" height="40" alt="DTC logo" style="display:block;width:40px;height:40px;border:0;">
                </td>
                @endif
                <td valign="middle">
                    <p style="margin:0;color:#0f4cdb;font-size:14px;font-weight:bold;line-height:1.5;">DTC Nexus</p>
                    <p style="margin:3px 0 0;color:#64748b;font-size:11px;line-height:1.5;">Danao Technological College</p>
                </td>
            </tr></table>
        </td></tr>
        <tr><td style="padding:24px;font-size:14px;line-height:1.65;">
            <h1 style="margin:0 0 16px;color:#0f172a;font-size:24px;font-weight:700;line-height:1.3;">{{ $heading }}</h1>
            {{ $slot }}
        </td></tr>
        @isset($fallback)
        <tr><td style="padding:16px 24px;background:#f8fafc;border-top:1px solid #edf0f4;">{{ $fallback }}</td></tr>
        @endisset
        <tr><td style="padding:16px 24px;border-top:1px solid #edf0f4;color:#64748b;font-size:11px;line-height:1.6;">Automated email · Replies aren't monitored.</td></tr>
    </table>
</td></tr></table>
</body></html>

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0 16px;"><tr><td bgcolor="#0f4cdb" style="border-radius:6px;">
    <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;color:#fff;font-size:14px;font-weight:bold;text-decoration:none;line-height:1.5;">{{ $label }}</a>
</td></tr></table>
@isset($expiry)
<p style="margin:0 0 18px;font-size:12px;color:#64748b;line-height:1.6;">Link expires in {{ $expiry }} minutes.</p>
@endisset
@slot('fallback')
<p style="margin:0 0 6px;font-size:11px;line-height:1.6;color:#64748b;">Button not working? Copy this link:</p>
<p style="margin:0;font-size:11px;line-height:1.6;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $url }}" style="color:#475569;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;">{{ $url }}</a></p>
@endslot

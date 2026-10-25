@component('emails.layout', ['heading' => 'Reset password', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Use the button below to choose a new password.</p>
<p style="margin:0;color:#475569;font-size:13px;word-break:break-word;">{{ $email }}</p>
@include('emails.action', ['url' => $resetUrl, 'label' => 'Reset password', 'expiry' => $expiresInMinutes])
<p style="margin:0;padding-top:16px;border-top:1px solid #edf0f4;color:#475569;font-size:12px;line-height:1.65;">Didn't request this? Ignore this email.<br>Your password will stay unchanged.</p>
@endcomponent

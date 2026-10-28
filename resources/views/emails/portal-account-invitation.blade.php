@component('emails.layout', ['heading' => 'Activate your portal account', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $profile->first_name }},</p>
<p style="margin:0 0 12px;">The Registrar has invited you to access your portal. Verify your email and set a password to continue.</p>
<p style="margin:0;color:#475569;">Student ID no.: <strong>{{ $profile->student_number }}</strong></p>
@include('emails.action', ['url' => $activationUrl, 'label' => 'Activate account'])
<p style="margin:0 0 12px;color:#64748b;font-size:12px;">This one-time link expires in {{ $expiresInMinutes }} minutes.</p>
<p style="margin:0;color:#475569;font-size:13px;">Didn't expect this? Contact the Registrar.</p>
@endcomponent
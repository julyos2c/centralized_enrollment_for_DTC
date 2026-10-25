@component('emails.layout', ['heading' => 'Application approved', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $application->first_name }},</p>
<p style="margin:0 0 16px;">Your application <strong>{{ $application->reference_no }}</strong> is approved. Set a password to activate your account.</p>
<p style="margin:0;color:#475569;word-break:break-word;">Account email: <strong>{{ $application->email }}</strong></p>
@include('emails.action', ['url' => $setupUrl, 'label' => 'Set password'])
<p style="margin:0;color:#64748b;font-size:12px;">This activation link expires in {{ $expiresInMinutes }} minutes. If it expires, <a href="{{ route('password.activation.request') }}" style="color:#0f4cdb;">request a new link</a>.</p>
@endcomponent
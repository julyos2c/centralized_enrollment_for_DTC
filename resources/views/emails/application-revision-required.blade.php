@component('emails.layout', ['heading' => 'Application revision needed', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $application->first_name }},</p>
<p style="margin:0 0 18px;">Please review the corrections for application <strong>{{ $application->reference_no }}</strong>.</p>
<div style="padding:14px 16px;border-left:2px solid #cbd5e1;background:#f8fafc;">
    <p style="margin:0 0 6px;font-size:12px;color:#64748b;">Registrar's remarks</p>
    <p style="margin:0;">{!! nl2br(e($application->remarks)) !!}</p>
</div>
<p style="margin:18px 0 0;">Use your reference number and application email to check the details.</p>
@include('emails.action', ['url' => route('public.application.status.form'), 'label' => 'Check status'])
@endcomponent
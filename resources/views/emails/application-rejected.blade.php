@component('emails.layout', ['heading' => 'Application not approved', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $application->first_name }},</p>
<p style="margin:0 0 18px;">Your application <strong>{{ $application->reference_no }}</strong> was not approved.</p>
<div style="padding:14px 16px;border-left:2px solid #cbd5e1;background:#f8fafc;">
    <p style="margin:0 0 6px;font-size:12px;color:#64748b;">Reason</p>
    <p style="margin:0;">{!! nl2br(e($application->remarks)) !!}</p>
</div>
<p style="margin:18px 0 0;">For questions, contact the Registrar's Office with your reference number.</p>
@endcomponent
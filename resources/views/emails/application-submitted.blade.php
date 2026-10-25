@component('emails.layout', ['heading' => 'Application received', 'emailMessage' => $message ?? null])
<p style="margin:0 0 18px;">Keep this reference number. You'll need it and your application email to check your status.</p>
<p style="margin:0;padding:14px 16px;border:1px solid #e2e8f0;border-radius:6px;font-size:20px;font-weight:bold;word-break:break-word;">{{ $referenceNo }}</p>
@include('emails.action', ['url' => route('public.application.status.form'), 'label' => 'Check status'])
<p style="margin:0;color:#64748b;font-size:12px;">Submission is not approval. The Registrar will review your application.</p>
@endcomponent
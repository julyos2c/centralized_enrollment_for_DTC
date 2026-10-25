@component('emails.layout', ['heading' => 'Document request update', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $documentRequest->user->name }},</p>
<p style="margin:0 0 18px;">{{ $eventMessage }}</p>
<p style="margin:0 0 8px;"><strong>{{ $documentRequest->document_label }}</strong> · {{ $documentRequest->copies }} {{ $documentRequest->copies === 1 ? 'copy' : 'copies' }}</p>
<p style="margin:0;color:#475569;">Status: {{ ucfirst(str_replace('_', ' ', $documentRequest->status)) }}<br>Payment: {{ ucfirst(str_replace('_', ' ', $documentRequest->payment_status)) }}</p>
@include('emails.action', ['url' => route('portal.dashboard'), 'label' => 'Open portal'])
@endcomponent

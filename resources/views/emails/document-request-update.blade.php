<h1>Document request update</h1>

<p>Hello {{ $documentRequest->user->name }},</p>

<p>{{ $eventMessage }}</p>

<p><strong>Request:</strong> {{ $documentRequest->document_label }} ({{ $documentRequest->copies }} {{ $documentRequest->copies === 1 ? 'copy' : 'copies' }})</p>
<p><strong>Current status:</strong> {{ ucfirst($documentRequest->status) }}</p>
<p><strong>Payment status:</strong> {{ ucfirst(str_replace('_', ' ', $documentRequest->payment_status)) }}</p>

<p>Sign in to DTC EMS to view your request details.</p>

@component('emails.layout', ['heading' => $eventTitle, 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $recipientName }},</p>
<p style="margin:0;">{{ $eventMessage }}</p>
@if($actionUrl)
    @include('emails.action', ['url' => $actionUrl, 'label' => $actionLabel])
@endif
@endcomponent
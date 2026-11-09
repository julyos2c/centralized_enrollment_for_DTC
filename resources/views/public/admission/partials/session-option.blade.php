@php $remaining = $session->remainingSeats(); @endphp
<label class="adm-session" for="{{ $idPrefix }}-session-{{ $session->id }}">
    <input type="radio" name="{{ $inputName }}" id="{{ $idPrefix }}-session-{{ $session->id }}" value="{{ $session->id }}" required>
    <div class="adm-session-date">{{ $session->exam_date->format('D, M j, Y') }}</div>
    <div class="adm-session-time">{{ \Carbon\Carbon::parse($session->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($session->end_time)->format('g:i A') }}</div>
    <div class="adm-session-venue">{{ $session->venue }}</div>
    <div class="adm-session-seats {{ $remaining <= max(3, (int) ceil($session->capacity * 0.1)) ? 'is-low' : '' }}">{{ $remaining }} seat{{ $remaining === 1 ? '' : 's' }} left</div>
</label>

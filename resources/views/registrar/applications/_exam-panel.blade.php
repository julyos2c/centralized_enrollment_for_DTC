{{-- Entrance exam summary for one application. Expects $application, $examMode, $examSessions. --}}
@php
    $activeBooking = $application->activeExamBooking;
    $history = $application->examBookings->sortByDesc('id');
@endphp
@if($examMode !== 'disabled' || $history->isNotEmpty())
<div class="card" id="exam">
    <div class="card-header">
        <span class="font-weight-bold">Entrance Examination</span>
        <span class="adm-chip {{ $examMode === 'required' ? 'is-warning' : '' }}">{{ ucfirst($examMode) }}</span>
    </div>
    <div class="card-body">
        @if($activeBooking)
            <div class="mb-2"><div class="text-muted small">Session</div>
                <a href="{{ route('registrar.exams.show', $activeBooking->session) }}">{{ $activeBooking->session->title }}</a>
                <div class="small">{{ $activeBooking->session->scheduleLabel() }} · {{ $activeBooking->session->venue }}</div>
            </div>
            <div class="mb-2"><div class="text-muted small">Booking status</div><span class="adm-chip is-info">{{ $activeBooking->statusLabel() }}</span></div>
            @if($activeBooking->result)
                <div class="mb-0"><div class="text-muted small">Result</div>
                    <span class="adm-chip {{ $activeBooking->result === 'passed' ? 'is-success' : 'is-danger' }}">{{ ucfirst($activeBooking->result) }}</span>
                    @if($activeBooking->score !== null) <span class="small">Score {{ rtrim(rtrim((string) $activeBooking->score, '0'), '.') }}</span>@endif
                    <div class="small text-muted">{{ $activeBooking->result_released_at ? 'Released ' . $activeBooking->result_released_at->format('M d, Y') : 'Not yet released to the applicant' }}</div>
                </div>
            @endif
        @else
            <p class="text-muted small mb-2">No active exam booking.</p>
            @if($application->isOpenForAdmissionActivity() && $examSessions->isNotEmpty())
                <form method="POST" action="{{ route('registrar.applications.exam.book', $application) }}">
                    @csrf
                    <label for="staff-book-session" class="small font-weight-bold">Book on the applicant's behalf</label>
                    <select id="staff-book-session" name="session_id" class="form-control form-control-sm mb-2" required>
                        <option value="">Choose a session…</option>
                        @foreach($examSessions as $session)
                            <option value="{{ $session->id }}">{{ $session->scheduleLabel() }} — {{ $session->remainingSeats() }} left</option>
                        @endforeach
                    </select>
                    <button type="submit" class="adm-btn adm-btn-sm adm-btn-primary w-100">Book and notify applicant</button>
                </form>
            @elseif($application->isOpenForAdmissionActivity())
                <p class="small mb-0"><a href="{{ route('registrar.exams.index') }}">Create an exam session</a> to allow booking.</p>
            @endif
        @endif

        @if($history->count() > ($activeBooking ? 1 : 0))
            <details class="adm-req-history mt-2">
                <summary>Booking history</summary>
                <ul>
                    @foreach($history as $past)
                        <li>{{ $past->session->exam_date->format('M d, Y') }} — {{ $past->statusLabel() }}@if($past->staff_remarks) ({{ $past->staff_remarks }})@endif</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>
@endif

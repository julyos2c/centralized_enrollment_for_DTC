@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Exam Roster')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <a href="{{ route('registrar.exams.index') }}" class="small"><i data-lucide="arrow-left" aria-hidden="true" style="width:14px;height:14px"></i> All sessions</a>
            <h4 class="mb-0 font-weight-bold u-text">{{ $session->title }}</h4>
            <p class="mb-0 u-text-secondary-sm">{{ $session->scheduleLabel() }} · {{ $session->venue }}</p>
        </div>
        <div class="adm-toolbar">
            <span class="adm-chip {{ ['open' => 'is-success', 'closed' => 'is-warning', 'cancelled' => 'is-danger'][$session->status] ?? '' }}">{{ ucfirst($session->status) }}</span>
            @if($session->status !== 'cancelled')
                <button type="button" class="adm-btn" data-toggle="collapse" data-target="#editSession" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="editSession"><i data-lucide="pencil" aria-hidden="true"></i> Edit</button>
                <form method="POST" action="{{ route('registrar.exams.status', $session) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $session->status === 'open' ? 'closed' : 'open' }}">
                    <button type="submit" class="adm-btn">{{ $session->status === 'open' ? 'Close booking' : 'Reopen booking' }}</button>
                </form>
                <button type="button" class="adm-btn adm-btn-danger" data-toggle="modal" data-target="#cancelSessionModal">Cancel session</button>
            @endif
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@endpush

@section('content')
@php
    $started = $session->hasStarted();
    $statusTones = ['pending' => 'is-warning', 'confirmed' => 'is-info', 'attended' => 'is-success', 'absent' => 'is-danger', 'rejected' => 'is-danger', 'cancelled' => '', 'rescheduled' => ''];
    $unreleased = $bookings->filter(fn ($b) => $b->status === 'attended' && $b->result && !$b->result_released_at)->count();
    $confirmedCount = (int) ($statusCounts['confirmed'] ?? 0);
@endphp
<div class="registrar-workspace">
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="collapse {{ $errors->any() ? 'show' : '' }}" id="editSession">
        <div class="card">
            <div class="card-header"><span class="font-weight-bold">Edit session</span><span class="u-text-secondary-sm">Changing date, time, or venue emails every booked applicant.</span></div>
            <form method="POST" action="{{ route('registrar.exams.update', $session) }}">
                @csrf @method('PUT')
                <div class="card-body">@include('registrar.admissions.exams._session-fields', ['session' => $session, 'defaultTerm' => []])</div>
                <div class="card-footer"><button type="submit" class="btn btn-primary">Save session</button></div>
            </form>
        </div>
    </div>

    <div class="adm-rate-grid mb-3">
        <div class="adm-rate"><span>Seats taken</span><strong>{{ $session->seated_bookings_count }} / {{ $session->capacity }}</strong><small>{{ $session->remainingSeats() }} remaining</small></div>
        <div class="adm-rate"><span>Awaiting approval</span><strong>{{ $statusCounts['pending'] ?? 0 }}</strong></div>
        <div class="adm-rate"><span>Confirmed</span><strong>{{ $confirmedCount }}</strong></div>
        <div class="adm-rate"><span>Attended / Absent</span><strong>{{ $statusCounts['attended'] ?? 0 }} / {{ $statusCounts['absent'] ?? 0 }}</strong></div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="d-flex flex-wrap" style="gap:8px" aria-label="Filter roster">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" style="max-width:220px" placeholder="Name or reference" aria-label="Search examinees">
                <select name="status" class="form-control form-control-sm" style="max-width:180px" aria-label="Booking status">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\EntranceExamBooking::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">Filter</button>
            </form>
            <div class="adm-toolbar">
                @if($started && $confirmedCount)
                    <form method="POST" action="{{ route('registrar.exams.attend-all', $session) }}" id="attend-all">
                        @csrf
                        <button type="button" class="adm-btn adm-btn-sm" data-dtc-confirm data-dtc-confirm-tone="neutral" data-dtc-confirm-title="Mark everyone present?" data-dtc-confirm-message="{subject} confirmed examinee(s) will be marked as attended. Mark absentees individually first." data-dtc-confirm-subject="{{ $confirmedCount }}" data-dtc-confirm-ok="Mark attended" data-dtc-confirm-form="#attend-all"><i data-lucide="user-check" aria-hidden="true"></i> Mark remaining attended</button>
                    </form>
                @endif
                @if($unreleased)
                    <form method="POST" action="{{ route('registrar.exams.release-all', $session) }}" id="release-all">
                        @csrf
                        <button type="button" class="adm-btn adm-btn-sm adm-btn-success" data-dtc-confirm data-dtc-confirm-tone="success" data-dtc-confirm-title="Release results?" data-dtc-confirm-message="{subject} recorded result(s) will become visible to applicants and they will be emailed." data-dtc-confirm-subject="{{ $unreleased }}" data-dtc-confirm-ok="Release" data-dtc-confirm-form="#release-all"><i data-lucide="send" aria-hidden="true"></i> Release {{ $unreleased }} result(s)</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            @unless($started)
                <div class="px-3 pt-3 small text-muted">Attendance and results can be recorded once the session starts ({{ $session->startsAt()->format('M d, Y g:i A') }}).</div>
            @endunless
            <div class="table-responsive">
                <table class="table mb-0 adm-table adm-stack-table">
                    <thead><tr><th>Applicant</th><th>Program</th><th>Status</th><th>Result</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            @php $app = $booking->application; @endphp
                            <tr>
                                <td data-label="Applicant">
                                    <a href="{{ route('registrar.applications.show', $app) }}" class="font-weight-bold">{{ $app->full_name }}</a>
                                    <div class="small text-muted">{{ $app->reference_no }}</div>
                                </td>
                                <td data-label="Program">{{ $app->program->code ?? '—' }}</td>
                                <td data-label="Status">
                                    <span class="adm-chip {{ $statusTones[$booking->status] ?? '' }}">{{ $booking->statusLabel() }}</span>
                                    @if($booking->status === 'rescheduled' && $booking->rescheduledTo)
                                        <div class="small text-muted">to {{ $booking->rescheduledTo->session->exam_date->format('M d') }}</div>
                                    @endif
                                    @if($booking->staff_remarks)<div class="small text-muted">{{ $booking->staff_remarks }}</div>@endif
                                </td>
                                <td data-label="Result">
                                    @if($booking->result)
                                        <span class="adm-chip {{ $booking->result === 'passed' ? 'is-success' : 'is-danger' }}">{{ ucfirst($booking->result) }}</span>
                                        @if($booking->score !== null)<span class="small">{{ rtrim(rtrim((string) $booking->score, '0'), '.') }}</span>@endif
                                        <div class="small text-muted">{{ $booking->result_released_at ? 'Released' : 'Not released' }}</div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div class="adm-row-actions">
                                        @if($booking->status === 'pending')
                                            <form method="POST" action="{{ route('registrar.exams.bookings.approve', $booking) }}">@csrf<button class="adm-btn adm-btn-sm adm-btn-success"><i data-lucide="check" aria-hidden="true"></i> Approve</button></form>
                                            <form method="POST" action="{{ route('registrar.exams.bookings.reject', $booking) }}" class="adm-inline-form">@csrf
                                                <input name="remarks" class="form-control" maxlength="500" required placeholder="Reason" aria-label="Reason for declining">
                                                <button class="adm-btn adm-btn-sm adm-btn-danger">Decline</button>
                                            </form>
                                        @endif

                                        @if($started && in_array($booking->status, ['confirmed', 'absent'], true))
                                            <form method="POST" action="{{ route('registrar.exams.bookings.attendance', $booking) }}">@csrf<input type="hidden" name="attendance" value="attended"><button class="adm-btn adm-btn-sm">Present</button></form>
                                        @endif
                                        @if($started && in_array($booking->status, ['confirmed', 'attended'], true) && !$booking->result_released_at)
                                            <form method="POST" action="{{ route('registrar.exams.bookings.attendance', $booking) }}">@csrf<input type="hidden" name="attendance" value="absent"><button class="adm-btn adm-btn-sm adm-btn-danger">Absent</button></form>
                                        @endif

                                        @if($booking->status === 'attended')
                                            <form method="POST" action="{{ route('registrar.exams.bookings.result', $booking) }}" class="adm-inline-form">@csrf
                                                <select name="result" class="form-control" required aria-label="Result">
                                                    <option value="">Result…</option>
                                                    <option value="passed" @selected($booking->result === 'passed')>Passed</option>
                                                    <option value="failed" @selected($booking->result === 'failed')>Did not pass</option>
                                                </select>
                                                <input name="score" type="number" step="0.01" min="0" max="9999.99" class="form-control" style="width:90px" value="{{ $booking->score }}" placeholder="Score" aria-label="Score">
                                                <input name="result_remarks" class="form-control" maxlength="500" value="{{ $booking->result_remarks }}" placeholder="Remarks (optional)" aria-label="Result remarks">
                                                <label class="small mb-0"><input type="checkbox" name="release" value="1"> Release now</label>
                                                <button class="adm-btn adm-btn-sm adm-btn-primary">Save</button>
                                            </form>
                                            @if($booking->result && !$booking->result_released_at)
                                                <form method="POST" action="{{ route('registrar.exams.bookings.release', $booking) }}">@csrf<button class="adm-btn adm-btn-sm adm-btn-success">Release</button></form>
                                            @endif
                                        @endif

                                        @if(in_array($booking->status, ['pending', 'confirmed', 'absent'], true) && $otherSessions->isNotEmpty())
                                            <form method="POST" action="{{ route('registrar.exams.bookings.reschedule', $booking) }}" class="adm-inline-form">@csrf
                                                <select name="session_id" class="form-control" required aria-label="Move to session">
                                                    <option value="">Move to…</option>
                                                    @foreach($otherSessions as $other)
                                                        <option value="{{ $other->id }}">{{ $other->exam_date->format('M d') }} {{ \Carbon\Carbon::parse($other->start_time)->format('g:i A') }} ({{ $other->remainingSeats() }} left)</option>
                                                    @endforeach
                                                </select>
                                                <button class="adm-btn adm-btn-sm">Move</button>
                                            </form>
                                        @endif

                                        @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                            <form method="POST" action="{{ route('registrar.exams.bookings.cancel', $booking) }}" class="adm-inline-form">@csrf
                                                <input name="remarks" class="form-control" maxlength="500" required placeholder="Cancellation reason" aria-label="Cancellation reason">
                                                <button class="adm-btn adm-btn-sm adm-btn-danger">Cancel</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="adm-empty">No bookings{{ request()->hasAny(['status', 'search']) ? ' match these filters' : ' yet' }}.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($session->status !== 'cancelled')
<div class="modal fade" id="cancelSessionModal" tabindex="-1" aria-labelledby="cancelSessionTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.exams.status', $session) }}">
                @csrf
                <input type="hidden" name="status" value="cancelled">
                <div class="modal-header"><h5 class="modal-title" id="cancelSessionTitle">Cancel this session?</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
                <div class="modal-body">
                    <p class="small">All pending and confirmed bookings are cancelled and each applicant is emailed the reason with an invitation to rebook. This cannot be undone.</p>
                    <label for="cancel-reason">Reason sent to applicants</label>
                    <textarea id="cancel-reason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Keep session</button>
                    <button type="submit" class="btn btn-danger">Cancel session</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

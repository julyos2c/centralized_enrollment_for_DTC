@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Entrance Exams')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Entrance Examinations</h4>
            <p class="mb-0 u-text-secondary-sm">Create exam sessions, control capacity, and manage examinees.</p>
        </div>
        <div class="adm-toolbar">
            <button type="button" class="adm-btn" data-toggle="collapse" data-target="#examSettings" aria-expanded="false" aria-controls="examSettings"><i data-lucide="settings-2" aria-hidden="true"></i> Exam policy</button>
            <button type="button" class="adm-btn adm-btn-primary" data-toggle="collapse" data-target="#newSession" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="newSession"><i data-lucide="plus" aria-hidden="true"></i> New session</button>
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@endpush

@section('content')
<div class="registrar-workspace">
    @if($settings['exam_mode'] === 'disabled')
        <div class="alert alert-info d-flex align-items-start" style="gap:10px" role="status">
            <i data-lucide="info" aria-hidden="true"></i>
            <div>The entrance exam step is <strong>disabled</strong>, so applicants cannot book sessions yet. Open <em>Exam policy</em> to enable it as optional or required.</div>
        </div>
    @endif
    @if($pendingBookings)
        <div class="alert alert-warning" role="status">{{ $pendingBookings }} booking request(s) are awaiting approval. Open a session roster to approve or decline them.</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    {{-- Policy --}}
    <div class="collapse" id="examSettings">
        <div class="card">
            <div class="card-header"><span class="font-weight-bold">Entrance exam policy</span></div>
            <form method="POST" action="{{ route('registrar.exams.settings') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label for="exam-mode">Entrance exam step</label>
                        <select id="exam-mode" name="exam_mode" class="form-control">
                            @foreach(\App\Support\AdmissionSettings::EXAM_MODES as $mode => $label)
                                <option value="{{ $mode }}" @selected($settings['exam_mode'] === $mode)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex flex-column" style="gap:8px">
                        <label class="mb-0 font-weight-normal"><input type="checkbox" name="documents_required_before_exam" value="1" @checked($settings['documents_required_before_exam'])> Applicants can book only after all <strong>required</strong> documents are approved</label>
                        <label class="mb-0 font-weight-normal"><input type="checkbox" name="booking_requires_approval" value="1" @checked($settings['booking_requires_approval'])> Bookings need Registrar approval (otherwise confirmed immediately)</label>
                        <label class="mb-0 font-weight-normal"><input type="checkbox" name="applicant_changes_allowed" value="1" @checked($settings['applicant_changes_allowed'])> Applicants may reschedule or cancel online</label>
                    </div>
                    <div class="form-group mt-3 mb-0" style="max-width:260px">
                        <label for="exam-cutoff">Changes close this many hours before the exam</label>
                        <input id="exam-cutoff" type="number" name="change_cutoff_hours" class="form-control" min="0" max="168" value="{{ $settings['change_cutoff_hours'] }}" required>
                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary">Save policy</button></div>
            </form>
        </div>
    </div>

    {{-- New session --}}
    <div class="collapse {{ $errors->any() ? 'show' : '' }}" id="newSession">
        <div class="card">
            <div class="card-header"><span class="font-weight-bold">New exam session</span></div>
            <form method="POST" action="{{ route('registrar.exams.store') }}">
                @csrf
                <div class="card-body">
                    @include('registrar.admissions.exams._session-fields', ['session' => null, 'defaultTerm' => $defaultTerm])
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary">Create session</button></div>
            </form>
        </div>
    </div>

    {{-- Sessions --}}
    <div class="card">
        <div class="card-header">
            <div class="btn-group btn-group-sm" role="group" aria-label="Session period">
                @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('registrar.exams.index', ['period' => $key]) }}" class="btn {{ $period === $key ? 'btn-primary' : 'btn-outline-secondary' }}" @if($period === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
            <span class="u-text-secondary-sm">{{ $sessions->total() }} session(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 adm-table adm-stack-table">
                    <thead><tr><th>Session</th><th>Schedule</th><th>Venue</th><th>Seats</th><th>Attendance</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        @forelse($sessions as $session)
                            @php $fill = $session->capacity ? min(100, round($session->seated_bookings_count / $session->capacity * 100)) : 0; @endphp
                            <tr>
                                <td data-label="Session"><strong>{{ $session->title }}</strong>@if($session->school_year)<div class="small text-muted">{{ $session->school_year }} {{ $session->semester }}</div>@endif</td>
                                <td data-label="Schedule">{{ $session->scheduleLabel() }}</td>
                                <td data-label="Venue">{{ $session->venue }}</td>
                                <td data-label="Seats">
                                    <div class="adm-meter"><span class="adm-meter-track"><span class="{{ $fill >= 100 ? 'is-full' : '' }}" style="width: {{ $fill }}%"></span></span><small>{{ $session->seated_bookings_count }}/{{ $session->capacity }}</small></div>
                                    @if($session->pending_count)<div class="small text-warning">{{ $session->pending_count }} awaiting approval</div>@endif
                                </td>
                                <td data-label="Attendance" class="small">{{ $session->attended_count }} attended · {{ $session->absent_count }} absent</td>
                                <td data-label="Status"><span class="adm-chip {{ ['open' => 'is-success', 'closed' => 'is-warning', 'cancelled' => 'is-danger'][$session->status] ?? '' }}">{{ ucfirst($session->status) }}</span></td>
                                <td data-label="Action" class="text-md-right"><a href="{{ route('registrar.exams.show', $session) }}" class="adm-btn adm-btn-sm">Open roster</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="adm-empty">No {{ $period === 'all' ? '' : $period }} exam sessions. Use “New session” to schedule one.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($sessions->hasPages())<div class="card-footer">{{ $sessions->links() }}</div>@endif
    </div>
</div>
@endsection

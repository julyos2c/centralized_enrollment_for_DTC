@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Admissions Overview')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Admissions Overview</h4>
            <p class="mb-0 u-text-secondary-sm">Applications, requirement review, entrance exams, and admission outcomes.</p>
        </div>
        <div class="adm-toolbar">
            <a href="{{ route('registrar.admissions.export.csv', request()->query()) }}" class="adm-btn"><i data-lucide="sheet" aria-hidden="true"></i> Export CSV</a>
            <a href="{{ route('registrar.admissions.export.pdf', request()->query()) }}" class="adm-btn"><i data-lucide="file-down" aria-hidden="true"></i> PDF report</a>
            <a href="{{ route('registrar.exams.index') }}" class="adm-btn adm-btn-primary"><i data-lucide="calendar-plus" aria-hidden="true"></i> Exam sessions</a>
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/dashboard-cards.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@endpush

@section('content')
@php
    $typeLabels = $types;
    $activeFilters = count($filters);
    $funnelMax = max(1, $stats['total']);
    $fmtRate = fn ($rate) => $rate === null ? '—' : $rate . '%';
@endphp
<div class="registrar-workspace">

    {{-- Filters --}}
    <form method="GET" action="{{ route('registrar.admissions.dashboard') }}" class="card" aria-label="Filter admissions">
        <div class="card-body">
            <div class="adm-filter-grid">
                <div>
                    <label for="f-sy">Academic year</label>
                    <select id="f-sy" name="school_year" class="form-control">
                        <option value="">All years</option>
                        @foreach($terms['school_years'] as $year)
                            <option value="{{ $year }}" @selected(($filters['school_year'] ?? '') === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-sem">Semester</label>
                    <select id="f-sem" name="semester" class="form-control">
                        <option value="">All semesters</option>
                        @foreach($terms['semesters'] as $semester)
                            <option value="{{ $semester }}" @selected(($filters['semester'] ?? '') === $semester)>{{ $semester }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-program">Program</label>
                    <select id="f-program" name="program_id" class="form-control">
                        <option value="">All programs</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected((string) ($filters['program_id'] ?? '') === (string) $program->id)>{{ $program->code }} — {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-status">Application status</label>
                    <select id="f-status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-type">Applicant type</label>
                    <select id="f-type" name="type" class="form-control">
                        <option value="">All types</option>
                        @foreach($typeLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-exam">Exam date</label>
                    <input id="f-exam" type="date" name="exam_date" class="form-control" value="{{ $filters['exam_date'] ?? '' }}">
                </div>
                <div class="adm-toolbar">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if($activeFilters)
                        <a href="{{ route('registrar.admissions.dashboard') }}" class="dtc-fl-clear">Clear ({{ $activeFilters }})</a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="adm-kpi-grid">
        <x-dtc.stat-card icon="users" color="primary" label="Total applicants" :value="number_format($stats['total'])"
            href="{{ route('registrar.applications.index', array_filter(['program_id' => $filters['program_id'] ?? null, 'status' => $filters['status'] ?? null, 'type' => $filters['type'] ?? null])) }}" link-text="View applications" />
        <x-dtc.stat-card icon="hourglass" color="warning" label="Pending applications" :value="number_format($stats['pending'])"
            :note="$stats['revision'] . ' awaiting applicant corrections'"
            href="{{ route('registrar.applications.index', ['status' => 'submitted']) }}" link-text="Start reviews" />
        <x-dtc.stat-card icon="file-search" color="info" label="Documents awaiting review" :value="number_format($stats['documents_pending'])"
            :metrics="['Approved' => number_format($stats['documents_approved']), 'Rejected' => number_format($stats['documents_rejected'])]" />
        <x-dtc.stat-card icon="calendar-clock" color="primary" label="Scheduled examinees" :value="number_format($stats['scheduled_examinees'])"
            :note="$stats['awaiting_booking_approval'] . ' booking(s) awaiting approval'"
            href="{{ route('registrar.exams.index') }}" link-text="Exam sessions" />
        <x-dtc.stat-card icon="badge-check" color="success" label="Admitted" :value="number_format($stats['approved'])"
            :metrics="['Not admitted' => number_format($stats['rejected']), 'Cancelled' => number_format($stats['cancelled'])]" />
    </div>

    <div class="row">
        {{-- Rates --}}
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="font-weight-bold">Outcomes</span></div>
                <div class="card-body">
                    <div class="adm-rate-grid mb-3">
                        <div class="adm-rate"><span>Acceptance rate</span><strong>{{ $fmtRate($stats['acceptance_rate']) }}</strong><small>admitted of decided</small></div>
                        <div class="adm-rate"><span>Enrollment conversion</span><strong>{{ $fmtRate($stats['conversion_rate']) }}</strong><small>{{ $stats['enrolled'] }} of {{ $stats['approved'] }} admitted enrolled</small></div>
                        <div class="adm-rate"><span>Exam attendance</span><strong>{{ $fmtRate($stats['attendance_rate']) }}</strong><small>{{ $stats['attended'] }} attended · {{ $stats['absent'] }} absent</small></div>
                        <div class="adm-rate"><span>Exam results</span><strong>{{ $stats['passed'] }} / {{ $stats['passed'] + $stats['failed'] }}</strong><small>passed · {{ $stats['failed'] }} did not pass</small></div>
                    </div>
                    <h3 class="adm-section-title">Application pipeline</h3>
                    <ul class="adm-funnel">
                        @foreach(['submitted' => 'Submitted', 'under_review' => 'Under review', 'revision_required' => 'Revision required', 'approved' => 'Admitted', 'rejected' => 'Not admitted', 'cancelled' => 'Cancelled'] as $key => $label)
                            @php $count = (int) ($stats['status'][$key] ?? 0); @endphp
                            <li>
                                <span>{{ $label }}</span>
                                <span class="adm-funnel-bar" aria-hidden="true"><span style="width: {{ round($count / $funnelMax * 100) }}%"></span></span>
                                <strong>{{ $count }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- Trend --}}
        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="font-weight-bold">Applications per month</span><span class="u-text-secondary-sm">last 6 months</span></div>
                <div class="card-body">
                    <div class="adm-chart-wrap"><canvas id="admTrendChart" role="img" aria-label="Applications submitted per month: {{ collect($trend)->map(fn ($v, $k) => "$k $v")->implode(', ') }}"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- By program --}}
        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="font-weight-bold">Applicants by program</span></div>
                <div class="card-body">
                    @if(empty($byProgram))
                        <div class="adm-empty">No applications match these filters.</div>
                    @else
                        <div class="adm-chart-wrap" style="height: {{ max(180, count($byProgram) * 44 + 60) }}px"><canvas id="admProgramChart" role="img" aria-label="Applicants and admitted per program"></canvas></div>
                        <table class="sr-only">
                            <caption>Applicants by program</caption>
                            <tr><th>Program</th><th>Applicants</th><th>Admitted</th></tr>
                            @foreach($byProgram as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['admitted'] }}</td></tr>@endforeach
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- By type --}}
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="font-weight-bold">Applicants by type</span></div>
                <div class="card-body">
                    @if(empty($byType))
                        <div class="adm-empty">No applications match these filters.</div>
                    @else
                        @php $typeMax = max(1, max($byType)); @endphp
                        <ul class="adm-funnel">
                            @foreach($byType as $type => $count)
                                <li>
                                    <span>{{ $typeLabels[$type] ?? ucwords(str_replace('_', ' ', $type)) }}</span>
                                    <span class="adm-funnel-bar" aria-hidden="true"><span style="width: {{ round($count / $typeMax * 100) }}%"></span></span>
                                    <strong>{{ $count }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Document review queue --}}
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="font-weight-bold">Document review queue</span><span class="u-text-secondary-sm">oldest first</span></div>
                @if($documentQueue->isEmpty())
                    <div class="adm-empty">No documents are waiting for review.</div>
                @else
                    <ul class="adm-list">
                        @foreach($documentQueue as $queued)
                            <li>
                                <div class="adm-list-main">
                                    <strong>{{ $queued->full_name }}</strong>
                                    <span>{{ $queued->reference_no }} · {{ $queued->program->code ?? '—' }} · {{ $queued->awaiting_count }} file(s) waiting</span>
                                </div>
                                <a href="{{ route('registrar.applications.show', $queued) }}#requirements" class="adm-btn adm-btn-sm">Review</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Upcoming sessions --}}
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">Upcoming entrance exams</span>
                    <span class="adm-chip {{ $examMode === 'disabled' ? '' : 'is-info' }}">Exam {{ $examMode }}</span>
                </div>
                @if($upcomingSessions->isEmpty())
                    <div class="adm-empty">No upcoming exam sessions. <a href="{{ route('registrar.exams.index') }}">Create one</a>.</div>
                @else
                    <ul class="adm-list">
                        @foreach($upcomingSessions as $session)
                            @php $fill = $session->capacity ? min(100, round($session->seated_bookings_count / $session->capacity * 100)) : 0; @endphp
                            <li>
                                <div class="adm-list-main">
                                    <strong>{{ $session->scheduleLabel() }}</strong>
                                    <span>{{ $session->venue }}@if($session->pending_count) · {{ $session->pending_count }} awaiting approval @endif</span>
                                </div>
                                <div class="adm-meter" title="{{ $session->seated_bookings_count }} of {{ $session->capacity }} seats">
                                    <span class="adm-meter-track"><span class="{{ $fill >= 100 ? 'is-full' : '' }}" style="width: {{ $fill }}%"></span></span>
                                    <small>{{ $session->seated_bookings_count }}/{{ $session->capacity }}</small>
                                </div>
                                <a href="{{ route('registrar.exams.show', $session) }}" class="adm-btn adm-btn-sm">Roster</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    const theme = typeof getDtcChartTheme === 'function' ? getDtcChartTheme() : { text: '#94A3B8', grid: '#F1F5F9', tooltipBg: '#1E293B', tooltipText: '#F8FAFC' };
    const register = typeof registerDtcChart === 'function' ? registerDtcChart : (c) => c;
    const tooltip = { backgroundColor: theme.tooltipBg, titleColor: theme.tooltipText, bodyColor: theme.tooltipText };
    const ticks = { font: { family: 'Poppins', size: 11 }, color: theme.text };

    const trend = @json($trend);
    const trendCanvas = document.getElementById('admTrendChart');
    if (trendCanvas) {
        register(new Chart(trendCanvas, {
            type: 'bar',
            data: { labels: Object.keys(trend), datasets: [{ label: 'Applications', data: Object.values(trend), backgroundColor: '#0F4CDB', borderRadius: 4, maxBarThickness: 36 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip },
                scales: {
                    x: { grid: { display: false }, ticks },
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: Object.assign({ precision: 0 }, ticks) }
                }
            }
        }));
    }

    const programs = @json($byProgram);
    const programCanvas = document.getElementById('admProgramChart');
    if (programCanvas && programs.length) {
        register(new Chart(programCanvas, {
            type: 'bar',
            data: {
                labels: programs.map(p => p.label),
                datasets: [
                    { label: 'Applicants', data: programs.map(p => p.total), backgroundColor: '#0F4CDB', borderRadius: 4, maxBarThickness: 18 },
                    { label: 'Admitted', data: programs.map(p => p.admitted), backgroundColor: '#16A34A', borderRadius: 4, maxBarThickness: 18 }
                ]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: theme.text, boxWidth: 12 } }, tooltip },
                scales: {
                    x: { beginAtZero: true, grid: { color: theme.grid }, ticks: Object.assign({ precision: 0 }, ticks) },
                    y: { grid: { display: false }, ticks }
                }
            }
        }));
    }
});
</script>
@endsection

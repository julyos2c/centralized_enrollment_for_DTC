<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admissions Report</title>
    @include('admin.reports._report-styles')
    <style>
        h2 { font-size: 12px; margin: 16px 0 8px; color: #0F4CDB; text-transform: uppercase; letter-spacing: .6px; }
        .filters { font-size: 9.5px; color: #64748B; margin: -6px 0 12px; }
    </style>
</head>
<body>
@php
    $rate = fn ($value) => $value === null ? '—' : $value . '%';
    $filterText = collect([
        'Year' => $filters['school_year'] ?? null,
        'Semester' => $filters['semester'] ?? null,
        'Program' => $filterProgram,
        'Status' => isset($filters['status']) ? ucwords(str_replace('_', ' ', $filters['status'])) : null,
        'Type' => isset($filters['type']) ? ($types[$filters['type']] ?? $filters['type']) : null,
        'Exam date' => $filters['exam_date'] ?? null,
    ])->filter()->map(fn ($v, $k) => "$k: $v")->implode(' · ');
@endphp

<table class="brand">
    <tr>
        <td>
            <div class="college">Danao Technological College</div>
            <div class="sub">DTC Nexus — Admissions</div>
        </td>
        <td class="title">
            <b>Admissions Report</b>
            <span>Generated {{ now()->format('F d, Y h:i A') }}</span>
        </td>
    </tr>
</table>
<div class="filters">Filters: {{ $filterText ?: 'None (all applications)' }}</div>

<table class="stats">
    <tr>
        <td><span class="num">{{ $stats['total'] }}</span><span class="lbl">Applicants</span></td>
        <td class="warn"><span class="num">{{ $stats['pending'] }}</span><span class="lbl">Pending</span></td>
        <td class="ok"><span class="num">{{ $stats['approved'] }}</span><span class="lbl">Admitted</span></td>
        <td class="bad"><span class="num">{{ $stats['rejected'] }}</span><span class="lbl">Not admitted</span></td>
    </tr>
</table>

<h2>Rates and requirements</h2>
<table class="data">
    <thead><tr><th>Measure</th><th class="num-col">Value</th><th>Basis</th></tr></thead>
    <tbody>
        <tr><td>Acceptance rate</td><td class="num-col">{{ $rate($stats['acceptance_rate']) }}</td><td class="muted">Admitted ÷ (admitted + not admitted)</td></tr>
        <tr><td>Enrollment conversion</td><td class="num-col">{{ $rate($stats['conversion_rate']) }}</td><td class="muted">{{ $stats['enrolled'] }} of {{ $stats['approved'] }} admitted applicants have an approved, paid enrollment</td></tr>
        <tr><td>Exam attendance</td><td class="num-col">{{ $rate($stats['attendance_rate']) }}</td><td class="muted">{{ $stats['attended'] }} attended, {{ $stats['absent'] }} absent</td></tr>
        <tr><td>Exam results</td><td class="num-col">{{ $stats['passed'] }} passed</td><td class="muted">{{ $stats['failed'] }} did not pass</td></tr>
        <tr><td>Documents approved</td><td class="num-col">{{ $stats['documents_approved'] }}</td><td class="muted">Current uploads</td></tr>
        <tr><td>Documents awaiting review</td><td class="num-col">{{ $stats['documents_pending'] }}</td><td class="muted">Current uploads</td></tr>
        <tr><td>Documents rejected</td><td class="num-col">{{ $stats['documents_rejected'] }}</td><td class="muted">Awaiting applicant replacement</td></tr>
        <tr><td>Revision required</td><td class="num-col">{{ $stats['revision'] }}</td><td class="muted">Applications waiting for applicant corrections</td></tr>
    </tbody>
</table>

<h2>Applicants per program</h2>
<table class="data">
    <thead><tr><th>Program</th><th class="num-col">Applicants</th><th class="num-col">Admitted</th></tr></thead>
    <tbody>
        @forelse($byProgram as $row)
            <tr><td>{{ $row['label'] }}</td><td class="num-col">{{ $row['total'] }}</td><td class="num-col">{{ $row['admitted'] }}</td></tr>
        @empty
            <tr><td colspan="3" class="empty">No applications match these filters.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Applicants per type</h2>
<table class="data">
    <thead><tr><th>Applicant type</th><th class="num-col">Applicants</th></tr></thead>
    <tbody>
        @forelse($byType as $type => $count)
            <tr><td>{{ $types[$type] ?? $type }}</td><td class="num-col">{{ $count }}</td></tr>
        @empty
            <tr><td colspan="2" class="empty">No applications match these filters.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Entrance examination sessions</h2>
<table class="data">
    <thead><tr><th>Date</th><th>Session</th><th>Venue</th><th class="num-col">Seats</th><th class="num-col">Attended</th><th class="num-col">Absent</th><th class="num-col">Passed</th><th>Status</th></tr></thead>
    <tbody>
        @forelse($sessions as $session)
            <tr>
                <td>{{ $session->scheduleLabel() }}</td>
                <td>{{ $session->title }}</td>
                <td>{{ $session->venue }}</td>
                <td class="num-col">{{ $session->seated_bookings_count }}/{{ $session->capacity }}</td>
                <td class="num-col">{{ $session->attended_count }}</td>
                <td class="num-col">{{ $session->absent_count }}</td>
                <td class="num-col">{{ $session->passed_count }}</td>
                <td>{{ ucfirst($session->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No exam sessions recorded.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">DTC Nexus · Confidential: contains applicant admission data. Handle according to the school's data privacy policy.</div>
</body>
</html>

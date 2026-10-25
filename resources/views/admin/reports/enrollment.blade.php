@php
    $badge = fn ($s) => match (strtolower((string) $s)) {
        'approved', 'paid', 'verified' => 'success',
        'pending', 'under review', 'submitted' => 'warning',
        'rejected', 'unpaid', 'failed', 'cancelled' => 'danger',
        default => 'neutral',
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Enrollment Report</title>
    @include('admin.reports._report-styles')
</head>
<body>

<table class="brand">
    <tr>
        <td>
            <div class="college">Danao Technological College</div>
            <div class="sub">Centralized Enrollment System</div>
        </td>
        <td class="title">
            <b>Enrollment Report</b>
            <span>Generated {{ now()->format('F d, Y h:i A') }}</span>
        </td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td><span class="num">{{ $enrollments->count() }}</span><span class="lbl">Total Submissions</span></td>
        <td class="ok"><span class="num">{{ $enrollments->where('status','approved')->where('is_paid',true)->count() }}</span><span class="lbl">Officially Enrolled</span></td>
        <td class="warn"><span class="num">{{ $enrollments->where('status','pending')->count() }}</span><span class="lbl">Pending Review</span></td>
        <td class="bad"><span class="num">{{ $enrollments->where('status','rejected')->count() }}</span><span class="lbl">Rejected</span></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th>Student Name</th>
            <th>Program</th>
            <th class="center">Year</th>
            <th class="center">Sem</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Date Submitted</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($enrollments as $i => $enrollment)
        <tr>
            <td class="muted">{{ $i + 1 }}</td>
            <td class="strong">{{ $enrollment->user->name }}</td>
            <td>{{ $enrollment->program->code }}</td>
            <td class="center">{{ $enrollment->year_level }}</td>
            <td class="center">{{ $enrollment->semester }}</td>
            <td><span class="badge badge-{{ $badge($enrollment->status) }}">{{ ucfirst(str_replace('_', ' ', $enrollment->status)) }}</span></td>
            <td>@php $pay = $enrollment->is_paid ? 'Paid' : 'Unpaid'; @endphp<span class="badge badge-{{ $badge($pay) }}">{{ $pay }}</span></td>
            <td class="muted">{{ $enrollment->created_at->format('M d, Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="8" class="empty">No enrollment records found.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    DTC Enrollment Management System — Confidential Document — {{ now()->format('Y') }}
</div>

</body>
</html>

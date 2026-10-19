<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: Arial, sans-serif; font-size: 12px; }
        body { margin: 20px; color: #1E293B; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0F4CDB; padding-bottom: 12px; }
        .header h1 { font-size: 20px; color: #0F4CDB; margin: 0 0 4px; }
        .header p  { font-size: 11px; color: #64748B; margin: 0; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 11px; color: #64748B; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0F4CDB; color: #fff; padding: 8px 10px;
            text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        tbody tr:nth-child(even) { background: #F8FAFC; }
        tbody td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 10px; font-weight: bold;
        }
        .badge-success  { background: #DCFCE7; color: #15803D; }
        .badge-warning  { background: #FEF9C3; color: #A16207; }
        .badge-danger   { background: #FEE2E2; color: #DC2626; }
        .badge-info     { background: #DBEAFE; color: #1D4ED8; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #94A3B8; }
        .summary { background: #EEF2FF; border-radius: 8px; padding: 12px; margin-bottom: 16px; display: flex; gap: 20px; }
        .summary-item { text-align: center; }
        .summary-item .num { font-size: 20px; font-weight: bold; color: #0F4CDB; }
        .summary-item .lbl { font-size: 10px; color: #64748B; }
    </style>
</head>
<body>

<div class="header">
    <h1>Danao Technological College</h1>
    <p>Enrollment Report — Generated {{ now()->format('F d, Y h:i A') }}</p>
</div>

<div class="summary">
    <div class="summary-item">
        <div class="num">{{ $enrollments->count() }}</div>
        <div class="lbl">Total Submissions</div>
    </div>
    <div class="summary-item">
        <div class="num">{{ $enrollments->where('status','approved')->where('is_paid',true)->count() }}</div>
        <div class="lbl">Officially Enrolled</div>
    </div>
    <div class="summary-item">
        <div class="num">{{ $enrollments->where('status','pending')->count() }}</div>
        <div class="lbl">Pending Review</div>
    </div>
    <div class="summary-item">
        <div class="num">{{ $enrollments->where('status','rejected')->count() }}</div>
        <div class="lbl">Rejected</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Program</th>
            <th>Year</th>
            <th>Semester</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Date Submitted</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($enrollments as $i => $enrollment)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $enrollment->user->name }}</td>
            <td>{{ $enrollment->program->code }}</td>
            <td>{{ $enrollment->year_level }}</td>
            <td>{{ $enrollment->semester }}</td>
            <td>
                @if($enrollment->status === 'approved')
                    <span class="badge badge-success">Approved</span>
                @elseif($enrollment->status === 'pending')
                    <span class="badge badge-info">Pending</span>
                @else
                    <span class="badge badge-danger">Rejected</span>
                @endif
            </td>
            <td>
                @if($enrollment->is_paid)
                    <span class="badge badge-success">Paid</span>
                @else
                    <span class="badge badge-warning">Unpaid</span>
                @endif
            </td>
            <td>{{ $enrollment->created_at->format('M d, Y') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    DTC Enrollment Management System — Confidential Document — {{ now()->format('Y') }}
</div>

</body>
</html>
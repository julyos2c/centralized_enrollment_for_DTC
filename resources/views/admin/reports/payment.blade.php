<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Report</title>
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
            <b>Payment Report</b>
            <span>Generated {{ now()->format('F d, Y h:i A') }}</span>
        </td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td class="ok"><span class="num">₱{{ number_format($totalRevenue, 2) }}</span><span class="lbl">Total Collection</span></td>
        <td><span class="num">{{ $payments->count() }}</span><span class="lbl">Total Transactions</span></td>
        <td><span class="num">₱{{ $verifiedCount > 0 ? number_format($totalRevenue / $verifiedCount, 2) : '0.00' }}</span><span class="lbl">Avg. per Verified Transaction</span></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th>Receipt No.</th>
            <th>Student</th>
            <th>Program</th>
            <th class="num-col">Amount</th>
            <th>Processed By</th>
            <th>Date Paid</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($payments as $i => $payment)
        <tr>
            <td class="muted">{{ $i + 1 }}</td>
            <td class="strong mono">{{ $payment->receipt_no }}</td>
            <td class="strong">{{ $payment->enrollment->user->name }}</td>
            <td>{{ $payment->enrollment->program->code }}</td>
            <td class="num-col strong">₱{{ number_format($payment->amount, 2) }}</td>
            <td>{{ $payment->cashier->name ?? '—' }}</td>
            <td class="muted">{{ optional($payment->paid_at)->format('M d, Y') ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="empty">No payments found.</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="4" style="text-align:right;">TOTAL COLLECTED</td>
            <td class="amt num-col">₱{{ number_format($totalRevenue, 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    DTC Enrollment Management System — Confidential Financial Report — {{ now()->format('Y') }}
</div>

</body>
</html>

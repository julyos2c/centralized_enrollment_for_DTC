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
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0F4CDB; color: #fff; padding: 8px 10px;
            text-align: left; font-size: 11px; text-transform: uppercase;
        }
        tbody tr:nth-child(even) { background: #F8FAFC; }
        tbody td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; }
        .total-row td { background: #EEF2FF; font-weight: bold; color: #0F4CDB; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #94A3B8; }
        .summary { background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
        .total { font-size: 20px; font-weight: bold; color: #15803D; }
    </style>
</head>
<body>

<div class="header">
    <h1>Danao Technological College</h1>
    <p>Payment Report — Generated {{ now()->format('F d, Y h:i A') }}</p>
</div>

<div class="summary">
    <strong>Total Collection:</strong>
    <span class="total">₱{{ number_format($totalRevenue, 2) }}</span>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total Transactions:</strong> {{ $payments->count() }}
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Per Transaction:</strong> ₱500.00 (fixed)
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Receipt No.</th>
            <th>Student</th>
            <th>Program</th>
            <th>Amount</th>
            <th>Processed By</th>
            <th>Date Paid</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($payments as $i => $payment)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $payment->receipt_no }}</strong></td>
            <td>{{ $payment->enrollment->user->name }}</td>
            <td>{{ $payment->enrollment->program->code }}</td>
            <td><strong>₱{{ number_format($payment->amount, 2) }}</strong></td>
            <td>{{ $payment->cashier->name }}</td>
            <td>{{ $payment->paid_at->format('M d, Y') }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4" style="text-align:right;">TOTAL COLLECTED:</td>
            <td colspan="3">₱{{ number_format($totalRevenue, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="footer">
    DTC Enrollment Management System — Confidential Financial Report — {{ now()->format('Y') }}
</div>

</body>
</html>
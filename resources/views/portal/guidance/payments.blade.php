@extends('adminlte::page')
@section('title', $title)
@section('content_header')
<div class="content">
    <x-dtc.page-header title="Payments" subtitle="Review your enrollment fees and payment verification status." icon="credit-card" />
</div>
@stop

@section('content')
<div class="content">
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table dtc-table mb-0"><thead><tr><th>Enrollment</th><th>Program</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead><tbody>
@forelse($enrollments as $enrollment)
@php $payment=$enrollment->payment; @endphp
<tr><td>#{{ $enrollment->id }}</td><td>{{ $enrollment->program->name ?? '—' }}</td><td>₱{{ number_format($payment->amount ?? 500, 2) }}</td><td><x-dtc.status-badge status="{{ $payment->status ?? ($enrollment->is_paid ? 'paid' : 'pending') }}" /></td><td>{{ $payment->receipt_no ?? '—' }}</td></tr>
@empty
<tr><td colspan="5"><x-dtc.empty-state icon="credit-card" title="No payment records" message="Your payment history will appear after an enrollment is created." /></td></tr>
@endforelse
</tbody></table></div></div></div>
</div>
@stop

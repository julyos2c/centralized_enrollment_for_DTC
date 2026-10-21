@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Payment Details')
@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text">Payment Details</h4>
    </div>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/payment-history.css') }}">
@endpush

@section('content')
@php
    $isStaff = in_array($role, ['admin', 'cashier', 'registrar'], true);
    $canSeeProcessor = in_array($role, ['admin', 'cashier'], true);
    $historyRoute = $role === 'admin' ? 'admin.transactions.index' : ($role === 'cashier' ? 'cashier.transactions.index' : ($role === 'registrar' ? 'registrar.transactions.index' : 'portal.transactions.index'));
    $tone = match($transaction->status) { 'verified' => 'success', 'rejected' => 'danger', 'pending', 'pending_verification' => 'warning', default => 'neutral' };
    $status = ['pending' => 'Pending', 'pending_verification' => 'Pending verification', 'unpaid' => 'Unpaid', 'verified' => 'Verified', 'rejected' => 'Rejected'][$transaction->status] ?? ucfirst(str_replace('_', ' ', $transaction->status));
    $fmt = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:i A') : null;
@endphp
<div class="dtc-payment-history ph-detail">
    <a class="ph-back" href="{{ route($historyRoute) }}"><i data-lucide="arrow-left" aria-hidden="true"></i><span>Payment history</span></a>

    <div class="ph-summary">
        <div class="ph-summary-main">
            <span class="ph-sub">{{ ucfirst($transaction->category) }}</span>
            <div class="ph-summary-amount">₱{{ number_format((float) $transaction->amount, 2) }}</div>
            <div class="ph-summary-ref dtc-payment-reference">{{ $transaction->transaction_key }}</div>
        </div>
        <span class="dtc-status-badge is-{{ $tone }}">{{ $status }}</span>
    </div>

    @if(!$isStaff && $transaction->status === 'rejected' && $transaction->rejection_reason)
        <div class="ph-note is-danger" role="alert"><i data-lucide="alert-circle" aria-hidden="true"></i><span>{{ $transaction->rejection_reason }}</span></div>
    @endif

    <dl class="ph-facts">
        @if($isStaff)
            <div><dt>Payer</dt><dd>{{ $transaction->payer }}<small>{{ $transaction->email }}</small></dd></div>
        @endif
        <div><dt>For</dt><dd>{{ $transaction->related }}</dd></div>
        <div><dt>Method</dt><dd>{{ $transaction->method ? ucfirst(str_replace('_', ' ', $transaction->method)) : '—' }}</dd></div>
        <div><dt>Reference no.</dt><dd class="dtc-payment-reference">{{ $transaction->reference ?: '—' }}</dd></div>
        <div><dt>Recorded</dt><dd>{{ $fmt($transaction->occurred_at) ?? '—' }}</dd></div>
        <div><dt>Verified</dt><dd>{{ $fmt($transaction->verified_at) ?? 'Not yet' }}</dd></div>
        @if($canSeeProcessor)
            <div><dt>Processed by</dt><dd>{{ $transaction->staff_name ?: '—' }}</dd></div>
        @endif
    </dl>
</div>
@stop

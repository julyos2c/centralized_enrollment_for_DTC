@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Fee Payments')

@section('content_header')
    <h4 class="mb-0 font-weight-bold u-text">Document Fee Payments</h4>
    <p class="mb-0 u-text-secondary-sm">Collect document fees and review GCash payments.</p>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/dtc-filters.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/cashier-payment-pages.css') }}">
@endpush
@section('content')
<div class="cashier-payment-page">
<form method="GET" action="{{ route('cashier.document-payments.index') }}" class="dtc-fl" aria-label="Filter document payments">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}" placeholder="Search name or request" aria-label="Search document payments">
        </div>
        <button type="button" class="dtc-fl-toggle" data-toggle="collapse" data-target="#documentPaymentsFilters" aria-controls="documentPaymentsFilters" aria-expanded="false">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i> Filters
        </button>
    </div>
    <div class="collapse" id="documentPaymentsFilters">
        <div class="dtc-fl-panel">
            <div class="dtc-fl-field">
                <label for="document-payment-status">Payment status</label>
                <select class="form-control" id="document-payment-status" name="status">
                    @foreach (['all' => 'All', 'unpaid' => 'Unpaid', 'pending_verification' => 'Pending GCash', 'rejected' => 'Rejected', 'verified' => 'Verified'] as $key => $label)
                        <option value="{{ $key }}" @selected($filter === $key)>{{ $label }} ({{ $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0) }})</option>
                    @endforeach
                </select>
            </div>
            @include('partials.table-rows', ['tableRecords' => $requests, 'tableId' => 'document-payments-table', 'fixedRows' => true])
            <div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('cashier.document-payments.index') }}" class="dtc-fl-clear">Clear</a>
            </div>
        </div>
    </div>
</form>
@if ($bundles->isNotEmpty())
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
        <strong class="u-text">Appointment payments</strong><span class="u-text-secondary-sm">{{ $bundles->total() }} payments</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table dtc-table dtc-docpay-table dtc-docpay-bundle-table mb-0">
            <thead><tr><th>Requester</th><th>Appointment</th><th>Method</th><th>Documents</th><th>Total</th><th>Payment status</th><th></th></tr></thead>
            <tbody>
            @foreach ($bundles as $bundle)
                @php
                    $bundleStatus = $bundle->status === 'pending'
                        ? ($bundle->payment_method === 'gcash' ? 'pending_verification' : 'unpaid')
                        : $bundle->status;
                    $bundleTone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$bundleStatus] ?? 'neutral';
                    $firstRequest = $bundle->appointment->documentRequests->first();
                    $documentCount = $bundle->appointment->documentRequests->count();
                @endphp
                <tr>
                    <td>
                        <div class="u-flex-center-gap-10 dtc-bundle-requester">
                            <x-dtc.user-avatar :user="$bundle->appointment->user" class="dtc-pay-avatar" />
                            <div class="dtc-bundle-requester-name">{{ $bundle->appointment->user->name }}</div>
                        </div>
                    </td>
                    <td>#{{ $bundle->appointment_id }} · {{ optional($bundle->appointment->slot)->date }}</td>
                    <td>{{ $bundle->payment_method === 'gcash' ? 'GCash QR' : 'Walk-in' }}</td>
                    <td class="dtc-bundle-document-count">{{ $documentCount }} {{ $documentCount === 1 ? 'document' : 'documents' }}</td>
                    <td class="dtc-pay-fee">₱{{ number_format($bundle->amount, 2) }}</td>
                    <td><span class="dtc-status-badge is-{{ $bundleTone }}"><span class="dtc-status-dot"></span>{{ $bundleStatus === 'pending_verification' ? 'Pending' : ucfirst($bundleStatus) }}</span></td>
                    <td>
                        @if ($firstRequest)
                            <a class="btn btn-sm btn-primary dtc-docpay-action" href="{{ route('cashier.document-payments.show', $firstRequest) }}">{{ $bundleStatus === 'verified' ? 'Details' : ($bundleStatus === 'unpaid' ? 'Record' : 'Review') }}</a>
                        @else
                            <span class="u-text-muted">No linked requests</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if ($bundles->hasPages())
        <div class="card-footer">{{ $bundles->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
    @endif
</div>
@endif
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
        <strong class="u-text">Document requests</strong><span class="u-text-secondary-sm">{{ $requests->total() }} records</span>
    </div>
    <div class="card-body p-0 table-responsive">

<table id="document-payments-table" class="table dtc-table dtc-docpay-table mb-0">
            <thead><tr><th>Requester</th><th>Document</th><th>Method</th><th>Fee</th><th>Payment status</th><th>Request status</th><th></th></tr></thead>
            <tbody>
            @forelse ($requests as $item)
                @php $tone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$item->payment_status] ?? 'neutral'; @endphp
                <tr>
                    <td>
                        <div class="u-flex-center-gap-10">
                            <x-dtc.user-avatar :user="$item->user" class="dtc-pay-avatar" />
                            <div style="min-width:0;">
                                <div style="font-size:13px; font-weight:600; color:var(--dtc-text);">{{ $item->user->name }}</div>
                                <div class="u-text-xs-secondary" style="overflow-wrap:anywhere;">{{ $item->user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $item->document_label }} <span class="u-text-xs-secondary">× {{ $item->copies }}</span></td>
                    <td>{{ $item->payment_method === 'gcash_qr' ? 'GCash QR' : 'Walk-in' }}</td>
                    <td class="dtc-pay-fee">₱{{ number_format($item->fee, 2) }}</td>
                    <td><span class="dtc-status-badge is-{{ $tone }}"><span class="dtc-status-dot"></span>{{ $item->payment_status === 'pending_verification' ? 'Pending' : ucfirst($item->payment_status) }}</span></td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td><a class="btn btn-sm btn-primary dtc-docpay-action" href="{{ route('cashier.document-payments.show', $item) }}">{{ $item->payment_status === 'verified' ? 'Details' : ($item->payment_status === 'unpaid' ? 'Record' : 'Review') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4 u-text-muted">No document payments match this filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <span class="u-text-secondary-sm">Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }}</span>
        @if ($requests->hasPages()) {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }} @endif
    </div>
</div>
</div>
@endsection

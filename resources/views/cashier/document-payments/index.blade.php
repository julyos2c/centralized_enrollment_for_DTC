@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Fee Payments')

@section('content_header')
    <h4 class="mb-0 font-weight-bold u-text">Document Fee Payments</h4>
    <p class="mb-0 u-text-secondary-sm">Record walk-in payments and review GCash claims. Verified payment status is shared with the Registrar and requester.</p>
@endsection

@section('content')
<div class="dtc-filter-bar">
    @foreach (['all' => 'All', 'unpaid' => 'Unpaid', 'pending_verification' => 'Pending GCash', 'rejected' => 'Rejected', 'verified' => 'Verified'] as $key => $label)
        <a class="dtc-filter-pill {{ $filter === $key ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['status' => $key]) }}">
            {{ $label }} <span>({{ $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0) }})</span>
        </a>
    @endforeach
</div>
<form method="GET" action="{{ route('cashier.document-payments.index') }}" class="dtc-pay-search" role="search">
    <input type="hidden" name="status" value="{{ $filter }}">
    <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
    <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}"
           placeholder="Search requester, email, request # or GCash ref…" autocomplete="off"
           aria-label="Search document payments">
    @if (request('search'))
        <a href="{{ route('cashier.document-payments.index', ['status' => $filter]) }}" class="dtc-pay-search-clear" aria-label="Clear search">
            <i data-lucide="x"></i>
        </a>
    @endif
</form>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
        <strong class="u-text">Document requests</strong><span class="u-text-secondary-sm">{{ $requests->total() }} records</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table dtc-table dtc-docpay-table mb-0">
            <thead><tr><th>Requester</th><th>Document</th><th>Method</th><th>Fee</th><th>Payment status</th><th>Request status</th><th></th></tr></thead>
            <tbody>
            @forelse ($requests as $item)
                @php $tone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$item->payment_status] ?? 'neutral'; @endphp
                <tr>
                    <td>
                        <div class="u-flex-center-gap-10">
                            <div class="dtc-pay-avatar">{{ strtoupper(mb_substr($item->user->name, 0, 1)) }}</div>
                            <div style="min-width:0;">
                                <div style="font-size:13px; font-weight:600; color:var(--dtc-text);">{{ $item->user->name }}</div>
                                <div class="u-text-xs-secondary" style="overflow-wrap:anywhere;">{{ $item->user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $item->document_label }} <span class="u-text-xs-secondary">× {{ $item->copies }}</span></td>
                    <td>{{ $item->payment_method === 'gcash_qr' ? 'GCash QR' : 'Walk-in' }}</td>
                    <td class="dtc-pay-fee">₱{{ number_format($item->fee, 2) }}</td>
                    <td><span class="dtc-status-badge is-{{ $tone }}"><span class="dtc-status-dot"></span>{{ ucwords(str_replace('_', ' ', $item->payment_status)) }}</span></td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td><a class="btn btn-sm btn-primary dtc-docpay-action" href="{{ route('cashier.document-payments.show', $item) }}">Review / record</a></td>
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
@endsection

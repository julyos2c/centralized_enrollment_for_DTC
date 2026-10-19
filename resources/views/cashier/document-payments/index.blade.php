@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Fee Payments')

@section('content_header')
    <h4 class="mb-0 font-weight-bold u-text">Document Fee Payments</h4>
    <p class="mb-0 u-text-secondary-sm">Record walk-in payments and review GCash claims. Verified payment status is shared with the Registrar and requester.</p>
@endsection

@section('content')
@if (session('success')) <div class="alert alert-success" role="status">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger" role="alert">{{ session('error') }}</div> @endif
<div class="d-flex flex-wrap" style="gap:8px; margin-bottom:16px;">
    @foreach (['all' => 'All', 'unpaid' => 'Unpaid', 'pending_verification' => 'Pending GCash', 'rejected' => 'Rejected', 'verified' => 'Verified'] as $key => $label)
        <a class="dtc-filter-pill {{ $filter === $key ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['status' => $key]) }}">
            {{ $label }} <span>({{ $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0) }})</span>
        </a>
    @endforeach
</div>
<form method="GET" action="{{ route('cashier.document-payments.index') }}" class="mb-3 d-flex flex-wrap" style="gap:8px; max-width:540px;">
    <input type="hidden" name="status" value="{{ $filter }}">
    <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search requester, email, request #, or GCash reference" aria-label="Search document payments">
    <button class="btn btn-primary" type="submit">Search</button>
</form>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
        <strong class="u-text">Document requests</strong><span class="u-text-secondary-sm">{{ $requests->total() }} records</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table dtc-table mb-0">
            <thead><tr><th>Requester</th><th>Document</th><th>Method</th><th>Fee</th><th>Payment status</th><th>Request status</th><th></th></tr></thead>
            <tbody>
            @forelse ($requests as $item)
                @php $tone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$item->payment_status] ?? 'neutral'; @endphp
                <tr>
                    <td><strong>{{ $item->user->name }}</strong><div class="u-text-xs-secondary">{{ $item->user->email }}</div></td>
                    <td>{{ $item->document_label }} <span class="u-text-xs-secondary">× {{ $item->copies }}</span></td>
                    <td>{{ $item->payment_method === 'gcash_qr' ? 'GCash QR' : 'Walk-in' }}</td>
                    <td>₱{{ number_format($item->fee, 2) }}</td>
                    <td><span class="dtc-status-badge is-{{ $tone }}">{{ ucwords(str_replace('_', ' ', $item->payment_status)) }}</span></td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td><a class="btn btn-sm btn-primary" href="{{ route('cashier.document-payments.show', $item) }}">Review / record</a></td>
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

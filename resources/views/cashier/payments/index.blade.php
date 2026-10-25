@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payments')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text" >Process Payments</h4>
        <p class="mb-0 u-text-secondary-sm" >
            Collect fees and review enrollment payments.
        </p>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cashier-payment-pages.css') }}">
@endpush

@section('content')
<div class="cashier-payment-page">
@if($receiptEnrollment)
    <button type="button" hidden tabindex="-1" aria-hidden="true" class="dtc-receipt-btn" data-auto-receipt data-url="{{ route('cashier.payments.receipt', $receiptEnrollment) }}"></button>
@endif

{{-- Search + Filters (same toolbar as Applications) --}}
@php
    $payFilterLabels = [
        'all'           => 'All statuses',
        'unpaid'        => 'Unpaid',
        'gcash_pending' => 'GCash Pending',
        'verified'      => 'Verified',
        'walk_in'       => 'Walk-in',
        'rejected'      => 'Rejected',
    ];
    $payFilterCount = ($filter && $filter !== 'all') ? 1 : 0;
@endphp
<form method="GET" action="{{ route('cashier.payments.index') }}" id="payments-filter-form" class="dtc-fl" aria-label="Filter payments">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" id="paymentSearch" name="search" value="{{ request('search') }}" class="dtc-pay-search-input"
                   placeholder="Search name, email, program…" autocomplete="off"
                   aria-label="Search enrollments">
            <button type="button" id="paymentSearchClear" class="dtc-pay-search-clear" aria-label="Clear search" hidden>
                <i data-lucide="x"></i>
            </button>
        </div>
        <button type="button" class="dtc-fl-toggle {{ $payFilterCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#paymentsFilterPanel"
                aria-expanded="{{ $payFilterCount ? 'true' : 'false' }}" aria-controls="paymentsFilterPanel">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filters</span>
            <span class="dtc-fl-count" id="paymentFilterCount" @if(!$payFilterCount) hidden @endif>{{ $payFilterCount }}</span>
        </button>
    </div>

    <div class="collapse {{ $payFilterCount ? 'show' : '' }}" id="paymentsFilterPanel">
        <div class="dtc-fl-panel">
            <div class="dtc-fl-field">
                <label for="filter-pay-status">Status</label>
                <select name="filter" id="filter-pay-status" class="form-control">
                    @foreach ($payFilterLabels as $key => $label)
                        <option value="{{ $key }}" {{ $filter === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.table-rows', ['tableRecords' => $enrollments, 'tableId' => 'enrollment-payments-table', 'fixedRows' => true])
            <div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('cashier.payments.index') }}" class="dtc-fl-clear" id="paymentFilterClear" @if(!$payFilterCount) hidden @endif>Clear all</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text" >Approved Enrollments</span>
        <span class="u-text-secondary-sm" id="paymentCount" data-total="{{ $enrollments->total() }}">{{ $enrollments->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">

<table id="enrollment-payments-table" class="table dtc-table dtc-payments-table mb-0">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Program</th>
                    <th>Year / Semester</th>
                    <th>Subjects</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Fee</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                <tr class="js-payment-row">
                    <td>
                        <div  class="u-flex-center-gap-10">
                            <x-dtc.user-avatar :user="$enrollment->user" style="width:32px; height:32px; border-radius:50%;
                                        background:linear-gradient(135deg,#22C55E,#059669);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;" />
                            <div>
                                <div style="font-size:13px; font-weight:500; color:var(--dtc-text);">
                                    {{ $enrollment->user->name }}
                                </div>
                                <div  class="u-text-xs-muted">
                                    {{ $enrollment->user->email }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--dtc-text); font-weight:500;">
                        {{ $enrollment->program->name }}
                    </td>
                    <td  class="u-text-secondary">
                        Year {{ $enrollment->year_level }} &middot; Sem {{ $enrollment->semester }}
                    </td>
                    <td>
                        <span class="badge badge-secondary">
                            {{ count($enrollment->subject_ids) }} subjects
                        </span>
                    </td>
                    <td  class="u-text-secondary">
                        @if ($enrollment->payment)
                            {{ $enrollment->payment->isWalkIn() ? 'Walk-in' : 'GCash' }}
                        @else
                            <span  class="u-text-muted">&mdash;</span>
                        @endif
                    </td>
                    <td>
                        @if (! $enrollment->payment)
                            <x-dtc.status-badge status="Unpaid" />
                        @elseif ($enrollment->payment->isPending())
                            <x-dtc.status-badge status="Pending" />
                        @elseif ($enrollment->payment->isVerified())
                            <x-dtc.status-badge status="Verified" />
                        @elseif ($enrollment->payment->isRejected())
                            <x-dtc.status-badge status="Rejected" />
                        @endif
                    </td>
                    <td class="dtc-pay-fee">
                        ₱{{ number_format($enrollment->payment->amount ?? \App\Models\Setting::get('enrollment_fee', 500), 2) }}
                    </td>
                    <td>
                        <div class="dtc-pay-actions">
                            <button type="button" class="dtc-review-btn dtc-payment-btn dtc-header-btn"
                                    data-url="{{ route('cashier.payments.show', $enrollment) }}">
                                <i data-lucide="banknote"></i>
                                <span class="dtc-header-btn-label">{{ $enrollment->payment?->isVerified() ? 'Details' : ($enrollment->payment?->isPending() ? 'Review' : 'Process') }}</span>
                            </button>
                            @if ($enrollment->is_paid)
                                <button type="button" class="dtc-review-btn dtc-receipt-btn dtc-header-btn"
                                        data-url="{{ route('cashier.payments.receipt', $enrollment) }}">
                                    <i data-lucide="receipt"></i>
                                    <span class="dtc-header-btn-label">Receipt</span>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 u-text-muted" >
                        No enrollments match the selected filter.
                    </td>
                </tr>
                @endforelse
                <tr id="paymentNoResults" hidden>
                    <td colspan="8" class="text-center py-4 u-text-muted">No enrollments match your search.</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card-footer u-panel-footer" >
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
            <small  class="u-text-secondary">
                Showing
                <strong>{{ $enrollments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $enrollments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $enrollments->total() }}</strong>
                results
            </small>

            @if ($enrollments->hasPages())
            <div>
                {{ $enrollments->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('cashier.payments._payment-modal')
@include('cashier.payments._receipt-modal')

</div>
@endsection

@push('js')
<script>
(function () {
    var input   = document.getElementById('paymentSearch');
    var clear   = document.getElementById('paymentSearchClear');
    var rows    = Array.prototype.slice.call(document.querySelectorAll('.js-payment-row'));
    var none    = document.getElementById('paymentNoResults');
    var counter = document.getElementById('paymentCount');
    if (!input || !rows.length) { return; }

    var sel       = document.getElementById('filter-pay-status');
    var fBadge    = document.getElementById('paymentFilterCount');
    var fToggle   = document.querySelector('[data-target="#paymentsFilterPanel"]');
    var fClear    = document.getElementById('paymentFilterClear');
    function syncFilterUi() {
        var n = sel && sel.value && sel.value !== 'all' ? 1 : 0;
        if (fBadge)  { fBadge.textContent = n; fBadge.hidden = n === 0; }
        if (fToggle) { fToggle.classList.toggle('has-filters', n > 0); }
        if (fClear)  { fClear.hidden = n === 0 && !input.value.trim(); }
    }
    if (sel) { sel.addEventListener('change', syncFilterUi); }

    var index = rows.map(function (r) { return r.textContent.replace(/\s+/g, ' ').toLowerCase(); });

    function apply() {
        var terms = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        var shown = 0;
        rows.forEach(function (row, i) {
            var match = terms.every(function (t) { return index[i].indexOf(t) !== -1; });
            row.hidden = !match;
            row.style.display = match ? '' : 'none';
            if (match) { shown++; }
        });
        none.hidden = shown !== 0;
        none.style.display = shown !== 0 ? 'none' : '';
        clear.hidden = !input.value;
        syncFilterUi();
        counter.textContent = terms.length
            ? shown + ' of ' + rows.length + ' shown'
            : counter.getAttribute('data-total') + ' total';
    }

    input.addEventListener('input', apply);
    clear.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { input.value = ''; apply(); } });
})();
</script>
@endpush

@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Requests')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text">Document Requests</h4>
        <p class="mb-0 u-text-secondary-sm dq-subtitle">
            Manage alumni requests. Admin-managed rates are saved with each request; payment verification is handled by the Cashier.
        </p>
    </div>
@endsection

@php
    $statusTone  = fn ($s) => match ($s) { 'released' => 'success', 'ready' => 'purple', 'processing' => 'info', default => 'warning' };
    $paymentTone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'];
    $paymentText = ['unpaid' => 'Unpaid', 'pending_verification' => 'Pending verification', 'verified' => 'Verified', 'rejected' => 'Rejected'];
    $pageItems   = $requests->getCollection();
    $statusCounts = $pageItems->countBy('status');
@endphp

@section('content')
<style>
    .dq-tools { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; margin-bottom: 14px; }
    .dq-search { position: relative; flex: 1 1 280px; max-width: 420px; }
    .dq-search input {
        width: 100%; height: 44px; padding: 0 40px 0 42px; border: 1px solid var(--dtc-border); border-radius: 10px;
        background: var(--dtc-surface); color: var(--dtc-text); font-size: 14px; outline: none; -webkit-appearance: none; appearance: none;
    }
    .dq-search input::-webkit-search-cancel-button { display: none; }
    .dq-search input::placeholder { color: var(--dtc-text-muted); }
    .dq-search input:focus { border-color: var(--dtc-primary); box-shadow: 0 0 0 3px rgba(15, 76, 219, .18); }
    .dq-search > svg { position: absolute; left: 14px; top: 50%; width: 18px; height: 18px; transform: translateY(-50%); color: var(--dtc-text-muted); pointer-events: none; }
    .dq-clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 32px; height: 32px; border: 0; border-radius: 8px;
                background: none; color: var(--dtc-text-secondary); cursor: pointer; font-size: 18px; line-height: 1; }
    .dq-clear[hidden] { display: none; }

    .dq-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; padding: 2px 0 4px; }
    .dq-chips::-webkit-scrollbar { display: none; }
    .dq-chip {
        flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px;
        border: 1px solid var(--dtc-border); border-radius: 999px; background: var(--dtc-surface);
        color: var(--dtc-text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
    }
    .dq-chip b { font-weight: 700; opacity: .7; }
    .dq-chip.is-active { background: var(--dtc-primary); border-color: var(--dtc-primary); color: #fff; }

    .dq-table { margin: 0; }
    .dq-table td { vertical-align: middle; }
    .dq-who { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .dq-avatar { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                 background: linear-gradient(135deg, #7C3AED, #A78BFA); color: #fff; font-weight: 700; font-size: 12px; }
    .dq-name { font-size: 13px; font-weight: 600; color: var(--dtc-text); }
    .dq-sub  { font-size: 12px; color: var(--dtc-text-secondary); overflow-wrap: anywhere; }
    .dq-doc  { font-size: 13px; font-weight: 600; color: var(--dtc-text); }
    .dq-fee  { font-size: 13px; font-weight: 700; color: var(--dtc-success); margin-bottom: 3px; }
    .dq-badge { white-space: nowrap; }
    .dq-lock { font-size: 12px; color: var(--dtc-text-muted); margin: -6px 0 14px; }
    .dq-empty[hidden] { display: none; }

    .dq-pay { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--dtc-text-secondary); white-space: nowrap; }
    .dq-pay::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--dtc-text-muted); }
    .dq-pay.is-success::before { background: #16A34A; }
    .dq-pay.is-warning::before { background: #D97706; }
    .dq-pay.is-info::before    { background: #2563EB; }
    .dq-pay.is-danger::before  { background: #DC2626; }
    .dq-purpose { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .dq-update {
        background: transparent !important; color: var(--dtc-primary) !important;
        border: 1px solid var(--dtc-primary) !important; font-weight: 600; padding: 5px 16px;
    }
    .dq-update:hover { background: var(--dtc-primary) !important; color: #fff !important; }

    @media (max-width: 767.98px) {
        .dq-subtitle { display: none; }
        .dq-search { max-width: none; flex-basis: 100%; }
        .dq-tools { gap: 10px; }
        .dq-table, .dq-table tbody { display: block; }
        .dq-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        .dq-table tbody tr.dq-row {
            display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 12px; row-gap: 10px; padding: 16px;
            grid-template-areas: "who status" "doc doc" "pick act";
            border-bottom: 1px solid var(--dtc-border-soft, var(--dtc-border));
        }
        .dq-table tbody tr.dq-row:last-child { border-bottom: 0; }
        .dq-table tbody tr.dq-row[hidden] { display: none; }
        .dq-table tbody td { display: block; padding: 0 !important; border: 0 !important; background: transparent !important; min-width: 0; }

        .dq-row td:nth-child(1) { grid-area: who; }
        .dq-row td:nth-child(4) { grid-area: status; align-self: start; }
        .dq-row td:nth-child(2) { grid-area: doc; padding: 10px 12px !important; border-radius: 10px; background: var(--dtc-surface-2, rgba(148,163,184,.08)) !important; }
        .dq-row td:nth-child(5) { grid-area: pick; min-width: 0 !important; align-self: center; }
        .dq-row td:nth-child(7) { grid-area: act; }
        .dq-row td:nth-child(3),
        .dq-row td:nth-child(6) { display: none !important; }   /* fee/payment + date move into the doc box / modal */

        .dq-avatar { width: 30px; height: 30px; flex-basis: 30px; font-size: 11px; }
        .dq-purpose { max-width: none; }
        .dq-update { min-height: 38px; }
        .dq-table tbody tr.dq-none, .dq-table tbody tr.dq-none td { display: block; width: 100%; }
        .dq-mobile-pay { display: flex !important; }
    }
    @media (min-width: 768px) { .dq-mobile-pay { display: none !important; } }
</style>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="dq-tools">
    <div class="dq-search">
        <i data-lucide="search" aria-hidden="true"></i>
        <input type="search" id="dqSearch" placeholder="Search alumni, email, document or purpose…" autocomplete="off" aria-label="Search document requests">
        <button type="button" class="dq-clear" id="dqClear" aria-label="Clear search" hidden>&times;</button>
    </div>
    <div class="dq-chips" role="tablist" aria-label="Filter by status">
        <button type="button" class="dq-chip is-active" data-filter="all">All <b>{{ $pageItems->count() }}</b></button>
        @foreach (['submitted' => 'Submitted', 'processing' => 'Processing', 'ready' => 'Ready', 'released' => 'Released'] as $key => $label)
            <button type="button" class="dq-chip" data-filter="{{ $key }}">{{ $label }} <b>{{ $statusCounts[$key] ?? 0 }}</b></button>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text">All Document Requests</span>
        <span class="u-text-secondary-sm" id="dqCount" data-total="{{ $requests->total() }}">{{ $requests->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table dq-table">
                <thead>
                    <tr>
                        <th>Alumni</th>
                        <th>Document</th>
                        <th>Fee &amp; payment</th>
                        <th>Status</th>
                        <th>Pickup</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $req)
                    <tr class="dq-row" data-status="{{ $req->status }}">
                        <td>
                            <div class="dq-who">
                                <div class="dq-avatar">{{ strtoupper(mb_substr($req->user->name, 0, 1)) }}</div>
                                <div style="min-width:0;">
                                    <div class="dq-name">{{ $req->user->name }}</div>
                                    <div class="dq-sub">{{ $req->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="dq-doc">{{ $req->document_label }} <span class="dq-sub">× {{ $req->copies }}</span></div>
                            <div class="dq-sub dq-purpose">{{ $req->purpose ?: 'No purpose given' }}</div>
                            <div class="dq-mobile-pay" style="align-items:center; justify-content:space-between; margin-top:8px;">
                                <span class="dq-fee" style="margin:0; font-size:15px;">₱{{ number_format($req->fee, 2) }}</span>
                                <span class="dq-pay is-{{ $paymentTone[$req->payment_status] ?? 'neutral' }}">{{ $paymentText[$req->payment_status] ?? ucfirst($req->payment_status) }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="dq-fee">₱{{ number_format($req->fee, 2) }}</div>
                            <span class="dq-pay is-{{ $paymentTone[$req->payment_status] ?? 'neutral' }}">{{ $paymentText[$req->payment_status] ?? ucfirst($req->payment_status) }}</span>
                        </td>
                        <td>
                            <span class="dtc-status-badge dq-badge is-{{ $statusTone($req->status) }}" style="font-weight:700;">
                                <span class="dtc-status-dot"></span>{{ ucfirst($req->status) }}
                            </span>
                        </td>
                        <td style="min-width:130px;">
                            @if ($req->appointment && $req->appointment->slot)
                                <div class="dq-name" style="font-weight:600;">
                                    {{ \Carbon\Carbon::parse($req->appointment->slot->date)->format('M d, Y') }}
                                </div>
                                <div class="dq-sub">
                                    {{ \Carbon\Carbon::parse($req->appointment->slot->start_time)->format('g:i A') }}
                                    · {{ ucfirst($req->appointment->status) }}
                                </div>
                            @else
                                <span class="dq-sub">Pickup not booked</span>
                            @endif
                        </td>
                        <td class="dq-sub" style="white-space:nowrap;">{{ $req->created_at->format('M d, Y') }}</td>
                        <td class="text-right">
                            @if ($req->status !== 'cancelled')
                                <button type="button" class="btn btn-sm btn-primary dq-update"
                                        data-toggle="modal" data-target="#statusModal{{ $req->id }}">
                                    Update
                                </button>
                            @else
                                <span class="dq-sub" aria-label="Cancelled requests cannot be updated">Closed</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    <tr class="dq-none dq-empty" id="dqNone" @if($requests->count()) hidden @endif>
                        <td colspan="7" class="text-center py-5" style="color:var(--dtc-text-muted); font-size:13px;">
                            <span id="dqNoneText">{{ $requests->count() ? 'No requests match your search.' : 'No document requests yet.' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap u-gap-15">
        <small class="u-text-secondary">
            Showing
            <strong>{{ $requests->firstItem() ?? 0 }}</strong>
            to
            <strong>{{ $requests->lastItem() ?? 0 }}</strong>
            of
            <strong>{{ $requests->total() }}</strong>
            requests
        </small>

        @if($requests->hasPages())
        <div>
            {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
</div>

{{-- Status modals (kept outside the table so they never inherit row styling) --}}
@foreach ($requests as $req)
    @if ($req->status !== 'cancelled')
    @php $releaseLocked = ! $req->is_paid || $req->payment_status !== 'verified'; @endphp
    <div class="modal fade" id="statusModal{{ $req->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('registrar.documents.status', $req) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Update Request Status</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="font-size:13px; color:var(--dtc-text-secondary); margin-bottom:16px;">
                            <strong>{{ $req->user->name }}</strong> —
                            {{ $req->document_label }}
                            ({{ $req->copies }} cop{{ $req->copies > 1 ? 'ies' : 'y' }})
                        </p>
                        <div class="form-group">
                            <label for="status-{{ $req->id }}">Status</label>
                            <select id="status-{{ $req->id }}" name="status" class="form-control" required>
                                @foreach(['submitted','processing','ready','released'] as $s)
                                    <option value="{{ $s }}"
                                        {{ $req->status === $s ? 'selected' : '' }}
                                        {{ $s === 'released' && $releaseLocked ? 'disabled' : '' }}>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($releaseLocked)
                            <div class="dq-lock">“Released” is locked until the Cashier verifies the payment.</div>
                        @endif
                        <div class="form-group">
                            <label for="remarks-{{ $req->id }}">Remarks <span style="color:var(--dtc-text-muted); font-weight:400;">(optional)</span></label>
                            <textarea id="remarks-{{ $req->id }}" name="remarks" class="form-control" rows="3"
                                      placeholder="Any notes for the alumni...">{{ $req->remarks }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach

<script>
(function () {
    var rows    = Array.prototype.slice.call(document.querySelectorAll('.dq-row'));
    var input   = document.getElementById('dqSearch');
    var clear   = document.getElementById('dqClear');
    var chips   = Array.prototype.slice.call(document.querySelectorAll('.dq-chip'));
    var none    = document.getElementById('dqNone');
    var noneTxt = document.getElementById('dqNoneText');
    var counter = document.getElementById('dqCount');
    if (!rows.length) { return; }

    var filter = 'all';
    var index  = rows.map(function (r) { return r.textContent.replace(/\s+/g, ' ').toLowerCase(); });

    function apply() {
        var terms = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        var shown = 0;
        rows.forEach(function (row, i) {
            var ok = (filter === 'all' || row.dataset.status === filter) &&
                     terms.every(function (t) { return index[i].indexOf(t) !== -1; });
            row.hidden = !ok;
            row.style.display = ok ? '' : 'none';
            if (ok) { shown++; }
        });
        none.hidden = shown !== 0;
        none.style.display = shown ? 'none' : '';
        noneTxt.textContent = 'No requests match your search.';
        clear.hidden = !input.value;
        counter.textContent = (terms.length || filter !== 'all')
            ? shown + ' of ' + rows.length + ' shown'
            : counter.getAttribute('data-total') + ' total';
    }

    input.addEventListener('input', apply);
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { input.value = ''; apply(); } });
    clear.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            filter = chip.dataset.filter;
            apply();
        });
    });
})();
</script>
@endsection

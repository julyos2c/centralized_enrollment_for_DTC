@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Review Document Payment')

@php
    $dr        = $documentRequest;
    $tone      = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$dr->payment_status] ?? 'neutral';
    $statusTxt = ucwords(str_replace('_', ' ', $dr->payment_status));
    $isGcash   = $dr->payment_method === 'gcash_qr';
    $methodTxt = $isGcash ? 'GCash QR' : 'Walk-in';
    $canWalkIn = ! $dr->is_paid && $dr->payment_status === 'unpaid' && ! $isGcash;
    $canGcash  = ! $dr->is_paid && $dr->payment_status === 'pending_verification' && $isGcash;
@endphp

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Review Document Payment</h4>
            <p class="mb-0 u-text-secondary-sm">Request #{{ $dr->id }} · {{ $dr->document_label }}</p>
        </div>
        <a href="{{ route('cashier.document-payments.index') }}" class="btn btn-secondary dtc-docpay-back">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to payments
        </a>
    </div>
@endsection

@section('content')
<style>
    .dpr-summary {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
        border: 1px solid var(--dtc-border); border-radius: 12px;
        background: var(--dtc-surface); margin-bottom: 18px; overflow: hidden;
    }
    .dpr-summary > div { padding: 14px 18px; min-width: 0; }
    .dpr-summary > div + div { border-left: 1px solid var(--dtc-border); }
    .dpr-label { display: block; font-size: 11px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: var(--dtc-text-muted); margin-bottom: 4px; }
    .dpr-fee { font-size: 24px; font-weight: 800; line-height: 1.1; color: var(--dtc-text); }
    .dpr-val { font-size: 14px; font-weight: 600; color: var(--dtc-text); }

    .dpr-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 18px; align-items: start; }
    .dpr-aside { position: sticky; top: 14px; }

    .dpr-section { padding: 16px 20px; }
    .dpr-section + .dpr-section { border-top: 1px solid var(--dtc-border); }
    .dpr-section h6 { margin: 0 0 10px; font-size: 12px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--dtc-text-secondary); }
    .dpr-rows { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 9px 14px; margin: 0; font-size: 13.5px; }
    .dpr-rows dt { font-weight: 500; color: var(--dtc-text-secondary); }
    .dpr-rows dd { margin: 0; color: var(--dtc-text); overflow-wrap: anywhere; }
    .dpr-sub { display: block; font-size: 12px; color: var(--dtc-text-secondary); }
    .dpr-ref { display: inline-flex; align-items: center; gap: 8px; }
    .dpr-ref code { font-size: 13px; padding: 2px 8px; border-radius: 6px; background: var(--dtc-primary-soft); color: var(--dtc-primary); }
    .dpr-copy { border: 0; background: none; padding: 2px 4px; font-size: 12px; color: var(--dtc-primary); cursor: pointer; }

    .dpr-note { display: flex; gap: 10px; padding: 12px 14px; margin-bottom: 14px; border-radius: 10px; font-size: 13px; line-height: 1.5;
                background: rgba(217, 119, 6, .10); border: 1px solid rgba(217, 119, 6, .35); color: var(--dtc-text); }
    .dpr-note svg { flex: 0 0 18px; width: 18px; height: 18px; margin-top: 1px; color: #D97706; }

    .dpr-money { position: relative; }
    .dpr-money > span { position: absolute; left: 14px; z-index: 2; line-height: 1; top: 50%; transform: translateY(-50%); color: var(--dtc-text-muted); font-weight: 600; pointer-events: none; }
    .dpr-money input.form-control { padding-left: 36px !important; font-weight: 600; }
    .dpr-hint { min-height: 18px; margin-top: 5px; font-size: 12px; color: var(--dtc-text-muted); }
    .dpr-hint.is-warn { color: #B45309; font-weight: 600; }
    .dpr-hint.is-ok { color: #15803D; }
    body.dtc-dark .dpr-hint.is-warn { color: #FBBF24; }
    body.dtc-dark .dpr-hint.is-ok { color: #4ADE80; }

    .dpr-proof { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; margin-bottom: 14px; border: 1px solid var(--dtc-border);
                 border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--dtc-primary); text-decoration: none; }
    .dpr-proof:hover { background: var(--dtc-primary-soft); text-decoration: none; }

    .dpr-reject { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--dtc-border); }
    .dpr-reject summary { cursor: pointer; font-size: 13px; font-weight: 600; color: var(--dtc-danger); list-style: none; }
    .dpr-reject summary::-webkit-details-marker { display: none; }
    .dpr-reject summary::before { content: "＋ "; }
    .dpr-reject[open] summary::before { content: "－ "; }
    .dpr-reject form { margin-top: 12px; }

    .dpr-done { text-align: center; padding: 26px 20px; }
    .dpr-done h5 { font-size: 16px; font-weight: 700; margin: 0 0 6px; color: var(--dtc-text); }
    .dpr-done p { margin: 0; font-size: 13.5px; color: var(--dtc-text-secondary); }

    @media (max-width: 991.98px) {
        .dpr-layout { grid-template-columns: minmax(0, 1fr); }
        .dpr-aside { position: static; order: -1; }   /* action first on tablet/phone */
    }
    @media (max-width: 575.98px) {
        .dpr-summary { grid-template-columns: 1fr 1fr; }
        .dpr-summary > div:first-child { grid-column: 1 / -1; border-bottom: 1px solid var(--dtc-border); }
        .dpr-summary > div:nth-child(3) { border-left: 1px solid var(--dtc-border); }
        .dpr-rows { grid-template-columns: 1fr; gap: 2px 0; }
        .dpr-rows dt { margin-top: 10px; font-size: 12px; }
        .dpr-section { padding: 14px 16px; }
        .dpr-action-btn { width: 100%; min-height: 46px; }
    }
</style>

@if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif

{{-- At-a-glance strip --}}
<div class="dpr-summary">
    <div>
        <span class="dpr-label">Fee due</span>
        <span class="dpr-fee">₱{{ number_format($dr->fee, 2) }}</span>
    </div>
    <div>
        <span class="dpr-label">Payment status</span>
        <span class="dtc-status-badge is-{{ $tone }}"><span class="dtc-status-dot"></span>{{ $statusTxt }}</span>
    </div>
    <div>
        <span class="dpr-label">Method</span>
        <span class="dpr-val">{{ $methodTxt }}</span>
    </div>
</div>

<div class="dpr-layout">

    {{-- ───────── Details ───────── --}}
    <div class="card mb-0">
        <div class="dpr-section">
            <h6>Request</h6>
            <dl class="dpr-rows">
                <dt>Requester</dt>
                <dd>{{ $dr->user->name }}<span class="dpr-sub">{{ $dr->user->email }}</span></dd>
                <dt>Document</dt>
                <dd>{{ $dr->document_label }} × {{ $dr->copies }}</dd>
                <dt>Purpose</dt>
                <dd>{{ $dr->purpose ?: 'Not provided' }}</dd>
                <dt>Request status</dt>
                <dd>{{ ucfirst($dr->status) }}</dd>
            </dl>
        </div>

        <div class="dpr-section">
            <h6>Payment record</h6>
            <dl class="dpr-rows">
                <dt>Amount received</dt>
                <dd>{{ $dr->amount_received !== null ? '₱' . number_format($dr->amount_received, 2) : 'Not recorded' }}</dd>

                @if ($dr->reference_number)
                    <dt>GCash reference</dt>
                    <dd>
                        <span class="dpr-ref">
                            <code id="gcashRef">{{ $dr->reference_number }}</code>
                            <button type="button" class="dpr-copy" data-copy="#gcashRef">Copy</button>
                        </span>
                    </dd>
                @endif
                @if ($dr->receipt_no)
                    <dt>Receipt / reference</dt>
                    <dd>{{ $dr->receipt_no }}</dd>
                @endif
                @if ($dr->verified_at)
                    <dt>Verified</dt>
                    <dd>{{ $dr->verified_at->format('M d, Y · g:i A') }}@if($dr->verifier)<span class="dpr-sub">by {{ $dr->verifier->name }}</span>@endif</dd>
                @endif
                @if ($dr->rejected_at)
                    <dt>Rejected</dt>
                    <dd>{{ $dr->rejected_at->format('M d, Y · g:i A') }}@if($dr->rejecter)<span class="dpr-sub">by {{ $dr->rejecter->name }}</span>@endif</dd>
                @endif
                @if ($dr->payment_rejection_reason)
                    <dt>Rejection reason</dt>
                    <dd>{{ $dr->payment_rejection_reason }}</dd>
                @endif
            </dl>
        </div>
    </div>

    {{-- ───────── Action ───────── --}}
    <div class="dpr-aside">
        <div class="card mb-0">
        @if ($canWalkIn)
            <div class="dpr-section">
                <h6>Record walk-in payment</h6>
                <p class="u-text-secondary-sm" style="margin-bottom:14px;">Confirm the funds were received at the Cashier’s Office, then enter the receipt or reference assigned under current college procedures.</p>
                <form method="POST" action="{{ route('cashier.document-payments.verify-walk-in', $dr) }}">@csrf
                    <div class="form-group">
                        <label for="walkin-amount">Amount received</label>
                        <div class="dpr-money"><span>₱</span>
                            <input id="walkin-amount" class="form-control js-amount" type="number" inputmode="decimal" name="amount_received" min="0.01" max="999999.99" step="0.01" value="{{ number_format($dr->fee, 2, '.', '') }}" data-fee="{{ number_format($dr->fee, 2, '.', '') }}" required>
                        </div>
                        <div class="dpr-hint js-amount-hint" aria-live="polite"></div>
                    </div>
                    <div class="form-group">
                        <label for="walkin-receipt">Official receipt / reference</label>
                        <input id="walkin-receipt" class="form-control" name="receipt_no" maxlength="100" required>
                    </div>
                    <button class="btn btn-primary dpr-action-btn" type="submit">Record and verify payment</button>
                </form>
            </div>

        @elseif ($canGcash)
            <div class="dpr-section">
                <h6>Verify GCash payment</h6>
                <div class="dpr-note" role="note">
                    <i data-lucide="alert-triangle" aria-hidden="true"></i>
                    <span>Match the reference and proof against the college’s actual GCash account records. A screenshot alone does not confirm that funds were received.</span>
                </div>
                @if ($dr->proof_of_payment)
                    <a class="dpr-proof" href="{{ route('cashier.document-payments.proof', $dr) }}" target="_blank" rel="noopener">
                        <i data-lucide="image"></i> Open submitted proof
                    </a>
                @endif
                <form method="POST" action="{{ route('cashier.document-payments.verify-gcash', $dr) }}">@csrf
                    <div class="form-group">
                        <label for="gcash-amount">Amount actually received</label>
                        <div class="dpr-money"><span>₱</span>
                            <input id="gcash-amount" class="form-control js-amount" type="number" inputmode="decimal" name="amount_received" min="0.01" max="999999.99" step="0.01" value="{{ number_format($dr->fee, 2, '.', '') }}" data-fee="{{ number_format($dr->fee, 2, '.', '') }}" required>
                        </div>
                        <div class="dpr-hint js-amount-hint" aria-live="polite"></div>
                    </div>
                    <div class="form-group">
                        <label for="gcash-receipt">Official receipt / reference <span class="u-text-muted" style="font-weight:400;">(optional)</span></label>
                        <input id="gcash-receipt" class="form-control" name="receipt_no" maxlength="100">
                    </div>
                    <button class="btn btn-success dpr-action-btn" type="submit">Confirm funds received</button>
                </form>

                <details class="dpr-reject" @if($errors->has('rejection_reason')) open @endif>
                    <summary>Can’t verify this claim?</summary>
                    <form method="POST" action="{{ route('cashier.document-payments.reject', $dr) }}">@csrf
                        <div class="form-group">
                            <label for="gcash-reason">Reason (the requester will be notified)</label>
                            <textarea id="gcash-reason" class="form-control" name="rejection_reason" maxlength="500" rows="3" required></textarea>
                        </div>
                        <button class="btn btn-outline-danger dpr-action-btn" type="submit">Reject and notify requester</button>
                    </form>
                </details>
            </div>

        @else
            <div class="dpr-done">
                <h5>No payment action needed</h5>
                <p>This request is {{ $dr->is_paid ? 'already verified' : str_replace('_', ' ', $dr->payment_status) }}. Payment verification can’t be repeated.</p>
            </div>
        @endif
        </div>
    </div>

</div>

<script>
(function () {
    // Live check of amount received against the fee due.
    document.querySelectorAll('.js-amount').forEach(function (input) {
        var hint = input.closest('.form-group').querySelector('.js-amount-hint');
        var fee  = parseFloat(input.dataset.fee);
        function fmt(n) { return '₱' + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function check() {
            var v = parseFloat(input.value);
            hint.className = 'dpr-hint js-amount-hint';
            if (isNaN(v)) { hint.textContent = ''; return; }
            var diff = Math.round((v - fee) * 100) / 100;
            if (diff === 0) { hint.textContent = 'Matches the fee due.'; hint.classList.add('is-ok'); }
            else if (diff < 0) { hint.textContent = 'Short by ' + fmt(diff) + ' — fee due is ' + fmt(fee) + '.'; hint.classList.add('is-warn'); }
            else { hint.textContent = 'Over by ' + fmt(diff) + ' — fee due is ' + fmt(fee) + '.'; hint.classList.add('is-warn'); }
        }
        input.addEventListener('input', check);
        check();
    });

    // Copy GCash reference
    document.querySelectorAll('.dpr-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.querySelector(btn.dataset.copy);
            if (!el || !navigator.clipboard) { return; }
            navigator.clipboard.writeText(el.textContent.trim()).then(function () {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            });
        });
    });
})();
</script>
@endsection

@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Review Document Payment')

@section('css')
<link rel="stylesheet" href="{{ asset('css/cashier-payment-pages.css') }}">
@endsection

@php
    $dr        = $documentRequest;
    $isBundle  = $bundlePayment !== null;
    $documentCount = $documentRequests->count();
    $isCombined = $isBundle && $documentCount > 1;
    $bundleStatus = $isBundle
        ? ($bundlePayment->status === 'pending' ? ($bundlePayment->payment_method === 'gcash' ? 'pending_verification' : 'unpaid') : $bundlePayment->status)
        : $dr->payment_status;
    $tone      = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$bundleStatus] ?? 'neutral';
    $statusTxt = ucwords(str_replace('_', ' ', $bundleStatus));
    $isGcash   = $isBundle ? $bundlePayment->payment_method === 'gcash' : $dr->payment_method === 'gcash_qr';
    $methodTxt = $isGcash ? 'GCash QR' : 'Walk-in';
    $canWalkIn = $isBundle
        ? $bundleStatus === 'unpaid'
        : ! $dr->is_paid && $dr->payment_status === 'unpaid' && ! $isGcash;
    $canGcash  = $isBundle
        ? $bundleStatus === 'pending_verification'
        : ! $dr->is_paid && $dr->payment_status === 'pending_verification' && $isGcash;
    if ($dr->paymentNotRequired() && (!$isBundle || (float) $bundlePayment->amount === 0.0)) {
        $canWalkIn = $canGcash = false; $statusTxt = 'No Payment Required';
    }
    $feeDue = $isBundle ? $bundlePayment->amount : $dr->fee;
    $proofPath = $isBundle ? $bundlePayment->proof_of_payment : $dr->proof_of_payment;
    $hasAvailableProof = $proofPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($proofPath);
@endphp

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Review Document Payment</h4>
            <p class="mb-0 u-text-secondary-sm">{{ $isBundle ? 'Appointment #' . $bundlePayment->appointment_id . ' · ' . ($isCombined ? 'Combined document payment' : 'Document payment') : 'Request #' . $dr->id . ' · ' . $dr->document_label }}</p>
        </div>
        <a href="{{ route('cashier.document-payments.index') }}" class="btn btn-secondary dtc-docpay-back">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to payments
        </a>
    </div>
@endsection

@section('content')
<div class="cashier-payment-page cashier-payment-review">
@if ($errors->any() && ! ($errors->has('payment') && ! $hasAvailableProof))
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

{{-- At-a-glance strip --}}
<div class="dpr-summary">
    <div>
        <span class="dpr-label">Fee due</span>
        <span class="dpr-fee">₱{{ number_format($feeDue, 2) }}</span>
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
            <h6>{{ $isCombined ? 'Requests' : 'Request' }}</h6>
            <dl class="dpr-rows">
                <dt>Requester</dt>
                <dd><span class="dpr-primary-value">{{ $dr->user->name }}</span><span class="dpr-sub">{{ $dr->user->email }}</span></dd>
                @if ($isBundle)
                    <dt>Appointment</dt>
                    <dd>#{{ $bundlePayment->appointment_id }} · {{ \Carbon\Carbon::parse($bundlePayment->appointment->slot->date)->format('M j, Y') }} · {{ ucfirst($bundlePayment->appointment->status) }}</dd>
                    <dt>{{ $isCombined ? 'Documents' : 'Document' }}</dt>
                    <dd class="dpr-document-list">
                        @foreach ($documentRequests as $item)
                            <span class="dpr-document-item">
                                <span>{{ $item->document_label }} × {{ $item->copies }} <span class="dpr-document-fee">₱{{ number_format($item->fee, 2) }}</span></span>
                                <span class="dpr-request-status">{{ ucfirst($item->status) }}</span>
                            </span>
                        @endforeach
                    </dd>
                @else
                    <dt>Document</dt>
                    <dd>{{ $dr->document_label }} × {{ $dr->copies }}</dd>
                @endif
                <dt>Purpose</dt>
                <dd>{{ $dr->purpose ?: 'Not provided' }}</dd>
                @unless ($isCombined)
                    <dt>Request status</dt>
                    <dd>{{ ucfirst($dr->status) }}</dd>
                @endunless
            </dl>
        </div>

        <div class="dpr-section">
            <h6>Payment record</h6>
            <dl class="dpr-rows dpr-payment-rows">
                <dt>Amount received</dt>
                <dd>{{ $isBundle ? ($bundlePayment->status === 'verified' ? '₱' . number_format($bundlePayment->amount, 2) : 'Not recorded') : ($dr->amount_received !== null ? '₱' . number_format($dr->amount_received, 2) : 'Not recorded') }}</dd>

                @if ($isBundle ? $bundlePayment->reference_number : $dr->reference_number)
                    <dt>GCash reference</dt>
                    <dd>
                        <span class="dpr-ref">
                            <code id="gcashRef">{{ $isBundle ? $bundlePayment->reference_number : $dr->reference_number }}</code>
                            <button type="button" class="dpr-copy" data-copy="#gcashRef" aria-label="Copy GCash reference" title="Copy GCash reference">
                                <i data-lucide="copy" aria-hidden="true"></i>
                                <span>Copy</span>
                            </button>
                        </span>
                    </dd>
                @endif
                @if ($isBundle ? $bundlePayment->receipt_no : $dr->receipt_no)
                    <dt>Receipt / reference</dt>
                    <dd>{{ $isBundle ? $bundlePayment->receipt_no : $dr->receipt_no }}</dd>
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
                <h6>{{ $isCombined ? 'Record combined walk-in payment' : 'Record walk-in payment' }}</h6>
                <p class="dpr-instructions">Confirm the funds were received at the Cashier’s Office, then enter the amount and official receipt or reference.</p>
                <form class="dpr-verification-form" method="POST" action="{{ $isBundle ? route('cashier.document-payments.bundles.verify', $bundlePayment) : route('cashier.document-payments.verify-walk-in', $dr) }}">@csrf
                    <div class="form-group dpr-form-group">
                        <label for="walkin-amount">Amount received</label>
                        <div class="dpr-money"><span>₱</span>
                            <input id="walkin-amount" class="form-control js-amount" type="number" inputmode="decimal" name="amount_received" min="0.01" max="999999.99" step="0.01" value="{{ number_format($feeDue, 2, '.', '') }}" data-fee="{{ number_format($feeDue, 2, '.', '') }}" aria-describedby="walkin-amount-hint" required>
                        </div>
                        <div id="walkin-amount-hint" class="dpr-hint js-amount-hint" aria-live="polite"></div>
                    </div>
                    <div class="form-group dpr-form-group">
                        <label for="walkin-receipt">Official receipt / reference</label>
                        <input id="walkin-receipt" class="form-control" name="receipt_no" maxlength="100" required>
                    </div>
                    <button class="btn btn-primary dpr-action-btn" type="submit">Record and verify payment</button>
                </form>
            </div>

        @elseif ($canGcash)
            <div class="dpr-section">
                <h6>{{ $isCombined ? 'Verify combined GCash payment' : 'Verify GCash payment' }}</h6>
                <div class="dpr-note" role="note">
                    <i data-lucide="alert-triangle" aria-hidden="true"></i>
                    <span>Match the reference and proof against the college’s actual GCash account records. A screenshot alone does not confirm that funds were received.</span>
                </div>
                @if ($hasAvailableProof)
                    <a class="dpr-proof" href="{{ $isBundle ? route('cashier.document-payments.bundles.proof', $bundlePayment) : route('cashier.document-payments.proof', $dr) }}" target="_blank" rel="noopener">
                        <i data-lucide="image"></i> Open submitted proof
                    </a>
                @endif
                @unless ($hasAvailableProof)
                    <div class="dpr-proof-unavailable" role="alert">
                        The uploaded proof file is unavailable. Don’t confirm this payment yet. Reject it and ask the requester to submit the proof again.
                    </div>
                @endunless
                @if ($hasAvailableProof)
                <form method="POST" action="{{ $isBundle ? route('cashier.document-payments.bundles.verify', $bundlePayment) : route('cashier.document-payments.verify-gcash', $dr) }}">@csrf
                    <div class="form-group">
                        <label for="gcash-amount">Amount actually received</label>
                        <div class="dpr-money"><span>₱</span>
                            <input id="gcash-amount" class="form-control js-amount" type="number" inputmode="decimal" name="amount_received" min="0.01" max="999999.99" step="0.01" value="{{ number_format($feeDue, 2, '.', '') }}" data-fee="{{ number_format($feeDue, 2, '.', '') }}" required>
                        </div>
                        <div class="dpr-hint js-amount-hint" aria-live="polite"></div>
                    </div>
                    <div class="form-group">
                        <label for="gcash-receipt">Official receipt / reference <span class="u-text-muted" style="font-weight:400;">(optional)</span></label>
                        <input id="gcash-receipt" class="form-control" name="receipt_no" maxlength="100">
                    </div>
                    <button class="btn btn-success dpr-action-btn" type="submit">Confirm funds received</button>
                </form>
                @endif

                <details class="dpr-reject" @if($errors->has('rejection_reason')) open @endif>
                    <summary>Can’t verify this claim?</summary>
                    <form method="POST" action="{{ $isBundle ? route('cashier.document-payments.bundles.reject', $bundlePayment) : route('cashier.document-payments.reject', $dr) }}">@csrf
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
                <p>This request is {{ $dr->paymentNotRequired() ? 'free ? No Payment Required' : ($dr->is_paid ? 'already verified' : str_replace('_', ' ', $dr->payment_status)) }}. Payment verification can’t be repeated.</p>
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
            var label = btn.querySelector('span');
            if (!el || !navigator.clipboard || !label) {
                if (label) label.textContent = 'Copy unavailable';
                return;
            }
            navigator.clipboard.writeText(el.textContent.trim()).then(function () {
                label.textContent = 'Copied';
                btn.classList.add('is-copied');
                btn.setAttribute('aria-label', 'GCash reference copied');
                setTimeout(function () {
                    label.textContent = 'Copy';
                    btn.classList.remove('is-copied');
                    btn.setAttribute('aria-label', 'Copy GCash reference');
                }, 1600);
            }).catch(function () {
                label.textContent = 'Copy failed';
                btn.setAttribute('aria-label', 'Could not copy GCash reference');
            });
        });
    });
})();
</script>
</div>
@endsection

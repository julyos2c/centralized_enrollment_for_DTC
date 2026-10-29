@extends('adminlte::page')
@include('partials.navbar')
@section('title', $paymentTitle)
@push('css')
<link rel="stylesheet" href="{{ asset_v('css/portal-payments.css') }}">
@endpush
@section('content')
<main class="portal-payment-page">
    @if(!empty($canSwitchToWalkIn))
        <form method="POST" action="{{ $resetPaymentAndBackUrl }}" class="portal-payment-back-form">
            @csrf
            <button type="submit" class="portal-payment-back">← Back to requests</button>
        </form>
    @else
        <a class="portal-payment-back" href="{{ $backUrl }}">← Back to requests</a>
    @endif
    <h1>{{ $paymentTitle }}</h1>
    <p class="portal-payment-description">{{ $description }}</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="portal-payment-card">
        <div class="portal-payment-total"><span>Fee</span><strong>₱{{ number_format($fee, 2) }}</strong></div>
        @if($status === 'not_required')
            <h2>No Payment Required</h2><p>The document fee is ?0.00. No payment, receipt, or Cashier verification is needed. The Registrar still processes and releases your request.</p>
        @elseif($status === 'verified')
            <h2>Payment verified</h2>
            @if($receipt)<p>Receipt: {{ $receipt }}</p>@endif
        @elseif($closed)
            <h2>Payment unavailable</h2><p>This request is closed. No further payment can be submitted.</p>
        @elseif($status === 'pending_verification')
            <h2>Payment under review</h2><p>The Cashier is checking your proof. No further action is needed.</p>
        @else
            @if($status === 'rejected')<div class="alert alert-danger" role="alert">{{ $remarks ?: 'Payment could not be verified. Review your details before resubmitting.' }}</div>@endif
            @if(($paymentMethod ?? 'walk_in') === 'walk_in' && !$canSubmit)
                <h2>Walk-in payment</h2><p>Pay in person at the Cashier's Office. Bring your request details and a valid ID. The Cashier records your payment; no proof upload needed.</p>
                @if(!empty($canSwitchToGcash))
                    <div class="portal-payment-switch-prompt">
                        <p>Prefer to pay online? You can switch back to walk-in before submitting GCash proof.</p>
                        <form method="POST" action="{{ $switchToGcashUrl }}" class="portal-payment-switch">
                            @csrf
                            <button type="submit" class="portal-payment-switch-button">Pay via GCash instead?</button>
                        </form>
                    </div>
                @endif
            @endif
        @endif
        @if($reference)<p class="portal-payment-description">GCash reference: {{ $reference }}</p>@endif
        @if($proofUrl)<a class="btn btn-outline-primary" href="{{ $proofUrl }}" target="_blank" rel="noopener">View submitted proof</a>@endif
    </section>
    @if($canSubmit)
        <section class="portal-payment-card">
            <h2>{{ $status === 'rejected' ? 'Resubmit GCash proof' : 'GCash payment' }}</h2>
            @if($gcashQrReady)
                <p class="u-text-sm-secondary-primary">Send your payment through GCash, then submit the reference number and payment proof below for Cashier verification.</p>
                <div class="dtc-gcash-mobile-note">
                    <div><strong>Paying on this phone?</strong> Use the GCash number below. You do not need to scan the QR.</div>
                </div>
                <div class="dtc-gcash-account">
                    <div>
                        <span>GCash account</span>
                        <strong>{{ $gcashName ?: 'Not configured' }}</strong>
                        <code>{{ $gcashNumber ?: 'Not configured' }}</code>
                    </div>
                    @if($gcashNumber)
                        <button type="button" class="dtc-gcash-copy" data-copy-value="{{ $gcashNumber }}" data-copy-label="GCash number">
                            <i data-lucide="copy" aria-hidden="true"></i>
                            <span>Copy number</span>
                        </button>
                    @endif
                </div>
                <div class="dtc-gcash-amount">
                    <span>Amount to send</span>
                    <strong>₱{{ number_format($fee, 2) }}</strong>
                    <button type="button" data-copy-value="{{ number_format((float) $fee, 2, '.', '') }}" data-copy-label="Amount">Copy</button>
                </div>
                <img src="{{ $qrUrl }}" alt="Official DTC GCash QR code"
                     style="width:150px;height:150px;object-fit:contain;display:block;margin:0 auto 12px;border:1px solid var(--dtc-border);border-radius:10px;">
                <a href="{{ $qrUrl }}?download=1" class="dtc-gcash-save-qr">
                    <i data-lucide="download" aria-hidden="true"></i> Save QR to phone
                </a>
                <p class="dtc-gcash-proof-guide">After paying, enter the reference number and upload the successful transaction screenshot.</p>
                <form method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="payment-reference">GCash Reference Number</label>
                        <input id="payment-reference" type="text" name="reference_number" value="{{ old('reference_number') }}"
                               class="form-control @error('reference_number') is-invalid @enderror"
                               maxlength="{{ $referenceMax }}" placeholder="e.g. 1234567890" required autocomplete="off">
                        @error('reference_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group mb-2">
                        <label for="payment-proof">Proof of Payment (screenshot / PDF, max 5 MB)</label>
                        <label class="dtc-file-label @error('proof_of_payment') is-invalid @enderror">
                            <i data-lucide="upload" style="width:15px;height:15px;margin-right:6px;flex-shrink:0;" aria-hidden="true"></i>
                            <span class="dtc-file-text">Choose file…</span>
                            <input id="payment-proof" type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf"
                                   class="dtc-file-input @error('proof_of_payment') is-invalid @enderror" required
                                   onchange="this.closest('label').querySelector('.dtc-file-text').textContent = this.files[0] ? this.files[0].name : 'Choose file…'">
                        </label>
                        @error('proof_of_payment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary portal-payment-submit">Submit proof</button>
                </form>
            @else
                <p>GCash is currently unavailable. Pay at the Cashier's Office.</p>
            @endif
        </section>
    @endif
</main>
@endsection

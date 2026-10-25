@extends('adminlte::page')
@include('partials.navbar')
@section('title', $paymentTitle)
@push('css')
<link rel="stylesheet" href="{{ asset('css/portal-payments.css') }}">
@endpush
@section('content')
<main class="portal-payment-page">
    <a class="portal-payment-back" href="{{ $backUrl }}">← Back to requests</a>
    <h1>{{ $paymentTitle }}</h1>
    <p class="portal-payment-description">{{ $description }}</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="portal-payment-card">
        <div class="portal-payment-total"><span>Fee</span><strong>₱{{ number_format($fee, 2) }}</strong></div>
        @if($status === 'verified')
            <h2>Payment verified</h2>
            @if($receipt)<p>Receipt: {{ $receipt }}</p>@endif
        @elseif($closed)
            <h2>Payment unavailable</h2><p>This request is closed. No further payment can be submitted.</p>
        @elseif($status === 'pending_verification')
            <h2>Payment under review</h2><p>The Cashier is checking your proof. No further action is needed.</p>
        @else
            @if($status === 'rejected')<div class="alert alert-danger" role="alert">{{ $remarks ?: 'Payment could not be verified. Review your details before resubmitting.' }}</div>@endif
            <h2>Walk-in payment</h2><p>Pay in person at the Cashier's Office. Bring your request details and a valid ID. The Cashier records your payment; no proof upload needed.</p>
        @endif
        @if($reference)<p class="portal-payment-description">GCash reference: {{ $reference }}</p>@endif
        @if($proofUrl)<a class="btn btn-outline-primary" href="{{ $proofUrl }}" target="_blank" rel="noopener">View submitted proof</a>@endif
    </section>
    @if($canSubmit)
        <section class="portal-payment-card">
            <h2>{{ $status === 'rejected' ? 'Resubmit GCash proof' : 'GCash payment' }}</h2>
            @if($gcashQrReady)
                <p>Send exactly <strong>₱{{ number_format($fee, 2) }}</strong> through GCash to the account below. Then submit the reference number and payment proof for Cashier verification.</p>
                <dl class="portal-payment-account"><dt>Account</dt><dd>{{ $gcashName ?: 'See official QR' }}</dd><dt>Number</dt><dd>{{ $gcashNumber ?: 'See official QR' }} @if($gcashNumber)<button type="button" data-copy-value="{{ $gcashNumber }}" data-copy-label="GCash number">Copy</button>@endif</dd></dl>
                <details class="portal-payment-qr"><summary>Show GCash QR</summary><img src="{{ $qrUrl }}" width="180" height="180" alt="Official GCash QR code"></details>
                <form method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group"><label for="payment-reference">GCash reference number</label><input id="payment-reference" class="form-control" name="reference_number" value="{{ old('reference_number') }}" maxlength="{{ $referenceMax }}" required autocomplete="off"></div>
                    <div class="form-group"><label for="payment-proof">Payment proof</label><input id="payment-proof" class="form-control-file" type="file" name="proof_of_payment" accept="image/jpeg,image/png,application/pdf" required><small>JPG, PNG or PDF, up to 5 MB.</small></div>
                    <button type="submit" class="btn btn-primary">Submit proof</button>
                </form>
            @else
                <p>GCash is currently unavailable. Pay at the Cashier's Office.</p>
            @endif
        </section>
    @endif
</main>
@endsection

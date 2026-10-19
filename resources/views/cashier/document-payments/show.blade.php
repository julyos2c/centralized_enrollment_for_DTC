@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Review Document Payment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div><h4 class="mb-0 font-weight-bold u-text">Review Document Payment</h4><p class="mb-0 u-text-secondary-sm">Request #{{ $documentRequest->id }} · {{ $documentRequest->document_label }}</p></div>
        <a href="{{ route('cashier.document-payments.index') }}" class="btn btn-secondary">Back to payments</a>
    </div>
@endsection

@section('content')
@if (session('success')) <div class="alert alert-success" role="status">{{ session('success') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif
<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card h-100"><div class="card-header"><strong class="u-text">Request and payment details</strong></div><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-5">Requester</dt><dd class="col-sm-7">{{ $documentRequest->user->name }}<br><span class="u-text-secondary-sm">{{ $documentRequest->user->email }}</span></dd>
                <dt class="col-sm-5">Document</dt><dd class="col-sm-7">{{ $documentRequest->document_label }} × {{ $documentRequest->copies }}</dd>
                <dt class="col-sm-5">Purpose</dt><dd class="col-sm-7">{{ $documentRequest->purpose ?: 'Not provided' }}</dd>
                <dt class="col-sm-5">Request status</dt><dd class="col-sm-7">{{ ucfirst($documentRequest->status) }}</dd>
                <dt class="col-sm-5">Payment method</dt><dd class="col-sm-7">{{ $documentRequest->payment_method === 'gcash_qr' ? 'GCash QR' : 'Walk-in' }}</dd>
                <dt class="col-sm-5">Payment status</dt><dd class="col-sm-7">{{ ucwords(str_replace('_', ' ', $documentRequest->payment_status)) }}</dd>
                <dt class="col-sm-5">Fee due</dt><dd class="col-sm-7"><strong>₱{{ number_format($documentRequest->fee, 2) }}</strong></dd>
                <dt class="col-sm-5">Amount received</dt><dd class="col-sm-7">{{ $documentRequest->amount_received !== null ? '₱' . number_format($documentRequest->amount_received, 2) : 'Not recorded' }}</dd>
                @if ($documentRequest->reference_number)
                    <dt class="col-sm-5">GCash reference</dt><dd class="col-sm-7">{{ $documentRequest->reference_number }}</dd>
                @endif
                @if ($documentRequest->proof_of_payment)
                    <dt class="col-sm-5">Submitted proof</dt><dd class="col-sm-7"><a href="{{ route('cashier.document-payments.proof', $documentRequest) }}" target="_blank" rel="noopener">Open private proof in new tab</a></dd>
                @endif
                @if ($documentRequest->receipt_no)
                    <dt class="col-sm-5">Receipt / reference</dt><dd class="col-sm-7">{{ $documentRequest->receipt_no }}</dd>
                @endif
                @if ($documentRequest->payment_rejection_reason)
                    <dt class="col-sm-5">Rejection reason</dt><dd class="col-sm-7">{{ $documentRequest->payment_rejection_reason }}</dd>
                @endif
                @if ($documentRequest->verified_at)
                    <dt class="col-sm-5">Last payment action</dt><dd class="col-sm-7">{{ $documentRequest->verified_at->format('M d, Y g:i A') }}@if($documentRequest->verifier) by {{ $documentRequest->verifier->name }}@endif</dd>
                @endif
                @if ($documentRequest->rejected_at)
                    <dt class="col-sm-5">Rejected</dt><dd class="col-sm-7">{{ $documentRequest->rejected_at->format('M d, Y g:i A') }}@if($documentRequest->rejecter) by {{ $documentRequest->rejecter->name }}@endif</dd>
                @endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-6 mb-3">
        @if (! $documentRequest->is_paid && $documentRequest->payment_status === 'unpaid' && $documentRequest->payment_method === 'walk_in')
            <div class="card"><div class="card-header"><strong class="u-text">Record walk-in payment</strong></div><div class="card-body">
                <p class="u-text-secondary-sm">Confirm that funds were received at the Cashier’s Office. Enter the receipt/reference assigned under current college procedures.</p>
                <form method="POST" action="{{ route('cashier.document-payments.verify-walk-in', $documentRequest) }}">@csrf
                    <div class="form-group"><label for="walkin-amount">Amount received (₱)</label><input id="walkin-amount" class="form-control" type="number" name="amount_received" min="0.01" max="999999.99" step="0.01" value="{{ number_format($documentRequest->fee, 2, '.', '') }}" required></div>
                    <div class="form-group"><label for="walkin-receipt">Official receipt / reference</label><input id="walkin-receipt" class="form-control" name="receipt_no" maxlength="100" required></div>
                    <button class="btn btn-primary" type="submit">Record and verify payment</button>
                </form>
            </div></div>
        @elseif (! $documentRequest->is_paid && $documentRequest->payment_status === 'pending_verification' && $documentRequest->payment_method === 'gcash_qr')
            <div class="alert alert-warning" role="note">Compare the reference and proof with the college’s actual GCash receiving account transaction records. An uploaded screenshot alone does not confirm receipt of funds.</div>
            <div class="card"><div class="card-header"><strong class="u-text">Verify GCash payment</strong></div><div class="card-body">
                <form method="POST" action="{{ route('cashier.document-payments.verify-gcash', $documentRequest) }}">@csrf
                    <div class="form-group"><label for="gcash-amount">Amount actually received (₱)</label><input id="gcash-amount" class="form-control" type="number" name="amount_received" min="0.01" max="999999.99" step="0.01" value="{{ number_format($documentRequest->fee, 2, '.', '') }}" required></div>
                    <div class="form-group"><label for="gcash-receipt">Official receipt / reference, if issued</label><input id="gcash-receipt" class="form-control" name="receipt_no" maxlength="100"></div>
                    <button class="btn btn-success" type="submit">Confirm funds received</button>
                </form>
                <hr>
                <form method="POST" action="{{ route('cashier.document-payments.reject', $documentRequest) }}">@csrf
                    <div class="form-group"><label for="gcash-reason">Reason the claim cannot be verified</label><textarea id="gcash-reason" class="form-control" name="rejection_reason" maxlength="500" rows="3" required></textarea></div>
                    <button class="btn btn-outline-danger" type="submit">Reject and notify requester</button>
                </form>
            </div></div>
        @else
            <div class="card"><div class="card-body"><h5 class="u-text">No payment action available</h5><p class="u-text-secondary mb-0">This request is {{ $documentRequest->is_paid ? 'already verified' : str_replace('_', ' ', $documentRequest->payment_status) }}. Payment verification cannot be repeated.</p></div></div>
        @endif
    </div>
</div>
@endsection

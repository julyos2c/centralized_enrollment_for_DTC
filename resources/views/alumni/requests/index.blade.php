@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Document Requests')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <h1>My document requests</h1>
            <p>See where each request is, what you owe, and when it’s ready to collect.</p>
        </div>
        <a href="{{ route('portal.documents.create') }}" class="dr-btn dr-btn--primary">
            <i data-lucide="plus" aria-hidden="true"></i> Request a document
        </a>
    </div>
@endsection

@php
    $items = method_exists($requests, 'getCollection') ? $requests->getCollection() : collect($requests);

    // Stage index on the four-step track, and which filter group a request belongs to.
    $stageOf   = ['submitted' => 0, 'processing' => 1, 'ready' => 2, 'released' => 3];
    $stageName = ['Submitted', 'Preparing', 'Ready', 'Released'];
    $groupOf   = fn ($s) => $s === 'ready' ? 'ready' : ($s === 'released' ? 'released' : 'progress');
    $badgeText = ['submitted' => 'Submitted', 'processing' => 'Being prepared', 'ready' => 'Ready for pickup', 'released' => 'Released'];
    $badgeTone = ['submitted' => 'warning', 'processing' => 'info', 'ready' => 'purple', 'released' => 'success'];

    $counts = ['all' => $items->count(), 'progress' => 0, 'ready' => 0, 'released' => 0];
    foreach ($items as $r) { $counts[$groupOf($r->status)]++; }
    $pickupNow = $items->filter(fn ($r) => $r->status === 'ready' && $r->payment_status === 'verified')->count();

    $payTone  = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'];
    $payLabel = ['unpaid' => 'Unpaid', 'pending_verification' => 'Payment under review', 'verified' => 'Paid', 'rejected' => 'Payment not verified'];
@endphp

@section('content')
<div class="dr dr-index" id="request-history">

    @if (session('success'))
        <div class="dr-alert dr-alert--ok" role="status">
            <i data-lucide="circle-check" aria-hidden="true"></i><div>{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="dr-alert dr-alert--error" role="alert">
            <i data-lucide="circle-alert" aria-hidden="true"></i><div>{{ session('error') }}</div>
        </div>
    @endif

    <div class="dtc-dashboard-overview dtc-dashboard-overview--four mb-3" aria-label="Document request summary">
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total requests</span>
            <strong>{{ $totalRequests }}</strong>
            <span class="dtc-overview-note">All document requests</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Ready for pickup</span>
            <strong>{{ $readyRequests }}</strong>
            <span class="dtc-overview-note">Payment verified</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Released</span>
            <strong>{{ $releasedDocs }}</strong>
            <span class="dtc-overview-note">Collected documents</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total fees</span>
            <strong>&#8369;{{ number_format($totalFees, 2) }}</strong>
            <span class="dtc-overview-note">Across all requests</span>
        </div>
    </div>

    <h2 class="dr-section-title">Request History</h2>

    @if ($items->isEmpty())
        <div class="dr-empty">
            <h2>No requests yet</h2>
            <p>Request a Transcript of Records, Diploma, Certification or True Copy. You can track every request here.</p>
            <a href="{{ route('portal.documents.create') }}" class="dr-btn dr-btn--primary">
                <i data-lucide="plus" aria-hidden="true"></i> Request a document
            </a>
        </div>
    @else
        @if ($pickupNow > 0)
            <div class="dr-ready" role="status">
                <span class="dr-ready-icon" aria-hidden="true"><i data-lucide="package-check"></i></span>
                <p>
                    <strong>{{ $pickupNow === 1 ? '1 document is' : $pickupNow.' documents are' }} ready to collect</strong>
                    Pick up at the Registrar’s Office. Bring your receipt and a valid ID.
                </p>
            </div>
        @endif

        <div class="dr-filters" role="group" aria-label="Filter requests" data-dr-filters>
            <button type="button" class="dr-filter" data-filter="all" aria-pressed="true">All <span class="dr-filter-count">{{ $counts['all'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="progress" aria-pressed="false">In progress <span class="dr-filter-count">{{ $counts['progress'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="ready" aria-pressed="false">Ready <span class="dr-filter-count">{{ $counts['ready'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="released" aria-pressed="false">Released <span class="dr-filter-count">{{ $counts['released'] }}</span></button>
        </div>

        <ul class="dr-list">
            @foreach ($items as $req)
                @php
                    $stage    = $stageOf[$req->status] ?? 0;
                    $paid     = $req->payment_status === 'verified';
                    $rejected = $req->payment_status === 'rejected';
                    $collect  = $req->status === 'ready' && $paid;
                    $copiesTx = $req->copies.($req->copies > 1 ? ' copies' : ' copy');
                @endphp
                <li class="dr-req {{ $rejected ? 'needs-fix' : ($collect ? 'needs-action' : '') }}" data-group="{{ $groupOf($req->status) }}">
                    <div class="dr-req-top">
                        <div>
                            <h2 class="dr-req-name">{{ $req->document_label }}</h2>
                            <p class="dr-req-meta">{{ $copiesTx }}, &#8369;{{ number_format($req->fee, 2) }}, requested {{ $req->created_at->format('M j, Y') }}</p>
                            @if ($req->purpose)
                                <p class="dr-req-purpose">Purpose: {{ $req->purpose }}</p>
                            @endif
                        </div>
                        <span class="dtc-status-badge is-{{ $badgeTone[$req->status] ?? 'neutral' }}" data-doc-status="{{ $req->status }}">
                            {{ $badgeText[$req->status] ?? ucfirst($req->status) }}
                        </span>
                    </div>

                    <ol class="dr-track" aria-label="Progress: step {{ $stage + 1 }} of 4, {{ $stageName[$stage] }}">
                        @foreach ($stageName as $i => $name)
                            <li class="{{ $i < $stage || ($i === $stage && $req->status === 'released') ? 'is-done' : '' }} {{ $i === $stage ? 'is-current' : '' }}"
                                @if ($i === $stage) aria-current="step" @endif>{{ $name }}</li>
                        @endforeach
                    </ol>

                    <p class="dr-step-note">
                        @if ($req->status === 'submitted')
                            Waiting for the Registrar to review your request.
                        @elseif ($req->status === 'processing')
                            The Registrar is preparing your document. We’ll notify you when it’s ready.
                        @elseif ($collect)
                            Ready at the Registrar’s Office. Bring your receipt and a valid ID.
                            <a href="{{ route('portal.appointments.create') }}">Book a pickup time</a>
                        @elseif ($req->status === 'ready')
                            The document is ready, but the Cashier hasn’t verified your payment yet. You can collect it once payment is verified.
                        @else
                            This request has been released to you.
                        @endif
                    </p>

                    @if ($req->remarks)
                        <div class="dr-remark" style="margin-top:12px;">
                            <i data-lucide="message-square-warning" aria-hidden="true"></i>
                            <span><strong>Note from the Registrar:</strong> {{ $req->remarks }}</span>
                        </div>
                    @endif

                    <div class="dr-pay">
                        <div class="dr-pay-line">
                            <span class="dtc-status-badge is-{{ $payTone[$req->payment_status] ?? 'neutral' }}">{{ $payLabel[$req->payment_status] ?? ucfirst(str_replace('_', ' ', $req->payment_status)) }}</span>
                            <span class="dr-pay-method">{{ $req->payment_method === 'gcash_qr' ? 'GCash QR' : 'Cashier’s Office' }}</span>
                        </div>

                        @if ($req->payment_status === 'unpaid')
                            <p class="dr-pay-text">Pay &#8369;{{ number_format($req->fee, 2) }} at the Cashier’s Office. Your payment counts once the Cashier records it.</p>
                        @elseif ($req->payment_status === 'pending_verification')
                            <p class="dr-pay-text">
                                Your proof is a claim until the Cashier confirms the funds arrived.
                                @if ($req->proof_of_payment)
                                    <a href="{{ route('portal.documents.payment.proof', $req) }}" target="_blank" rel="noopener">View submitted proof</a>
                                @endif
                            </p>
                        @elseif ($paid)
                            <p class="dr-pay-text">Received &#8369;{{ number_format($req->amount_received, 2) }}@if ($req->receipt_no), receipt {{ $req->receipt_no }}@endif.</p>
                        @elseif ($rejected)
                            <div class="dr-remark" role="alert">
                                <i data-lucide="circle-alert" aria-hidden="true"></i>
                                <span>{{ $req->payment_rejection_reason ?: 'The Cashier couldn’t verify this payment. Contact the Cashier for next steps.' }}</span>
                            </div>
                            @if ($req->payment_method === 'gcash_qr')
                                <details class="dr-resubmit" open>
                                    <summary>Resubmit your payment proof</summary>
                                    <form method="POST" action="{{ route('portal.documents.payment.submit', $req) }}" enctype="multipart/form-data" class="dr-resubmit-body">
                                        @csrf
                                        <p class="dr-help">Pay exactly &#8369;{{ number_format($req->fee, 2) }}, then enter the new reference and upload the receipt.</p>
                                        <div class="dr-resubmit-qr"><img src="{{ route('portal.documents.gcash.qr') }}" alt="Official DTC GCash QR code" loading="lazy"></div>
                                        <div class="dr-field">
                                            <label class="dr-label" for="payment-reference-{{ $req->id }}">GCash reference number</label>
                                            <input id="payment-reference-{{ $req->id }}" type="text" name="reference_number" class="dr-input" maxlength="100" required autocomplete="off" inputmode="numeric">
                                        </div>
                                        <div class="dr-field">
                                            <span class="dr-label" id="proof-label-{{ $req->id }}">Receipt screenshot</span>
                                            <label class="dr-file" for="payment-proof-{{ $req->id }}">
                                                <input id="payment-proof-{{ $req->id }}" type="file" name="proof_of_payment" class="dr-visually-hidden" accept="image/jpeg,image/png,application/pdf" required aria-labelledby="proof-label-{{ $req->id }}">
                                                <span class="dr-file-btn">Choose file</span>
                                                <span class="dr-file-name">JPG, PNG or PDF, up to 5 MB</span>
                                            </label>
                                        </div>
                                        <button type="submit" class="dr-btn dr-btn--primary">Resubmit proof</button>
                                    </form>
                                </details>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="dr-empty dr-empty--filter" id="drFilterEmpty" role="status">
            <p>Nothing here yet. Try another filter.</p>
        </div>

        @if (method_exists($requests, 'links'))
            <div style="margin-top:20px;">{{ $requests->links() }}</div>
        @endif
    @endif
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
@endsection

@section('js')
    <script src="{{ asset('js/document-request.js') }}" defer></script>
    <script>
        // Show the chosen file name on the resubmit form.
        document.querySelectorAll('.dr-resubmit input[type="file"]').forEach(function (input) {
            input.addEventListener('change', function () {
                var label = input.closest('.dr-file');
                var name = label.querySelector('.dr-file-name');
                var file = input.files && input.files[0];
                if (file && file.size > 5 * 1024 * 1024) {
                    input.value = '';
                    name.textContent = 'That file is over 5 MB. Choose a smaller one.';
                    label.classList.remove('has-file');
                    return;
                }
                name.textContent = file ? file.name : 'JPG, PNG or PDF, up to 5 MB';
                label.classList.toggle('has-file', !!file);
            });
        });
    </script>
@endsection

@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Document Requests')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <h1>My document requests</h1>
            <p>Track status, payment and pickup.</p>
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
<div class="dr dr-index dr-alumni-history" id="request-history">


    <details class="dr-history-summary">
        <summary>Request overview <span>{{ $totalRequests }} total &middot; {{ $readyRequests }} ready</span></summary>
    <div class="dtc-dashboard-overview dtc-dashboard-overview--four mb-3" aria-label="Document request summary">
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total requests</span>
            <strong>{{ $totalRequests }}</strong>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Ready for pickup</span>
            <strong>{{ $readyRequests }}</strong>
            <span class="dtc-overview-note">Payment verified</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Released</span>
            <strong>{{ $releasedDocs }}</strong>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total fees</span>
            <strong>&#8369;{{ number_format($totalFees, 2) }}</strong>
        </div>
    </div>

    </details>

    <h2 class="dr-section-title">Your requests</h2>

    @if ($items->isEmpty())
        <div class="dr-empty">
            <h2>No requests yet</h2>
            <p>Your requests will appear here.</p>
            <a href="{{ route('portal.documents.create') }}" class="dr-btn dr-btn--primary">
                <i data-lucide="plus" aria-hidden="true"></i> Request a document
            </a>
        </div>
    @else
        @if ($pickupNow > 0)
            <p class="dr-collection-hint">For pickup, bring your receipt and a valid ID.</p>
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
                        </div>
                    </div>

                    <ol class="dr-track" aria-label="Progress: step {{ $stage + 1 }} of 4, {{ $stageName[$stage] }}">
                        @foreach ($stageName as $i => $name)
                            <li class="{{ $i < $stage || ($i === $stage && $req->status === 'released') ? 'is-done' : '' }} {{ $i === $stage ? 'is-current' : '' }}"
                                @if ($i === $stage) aria-current="step" @endif>{{ $name }}</li>
                        @endforeach
                    </ol>

                    @if (($collect && !in_array($req->appointment?->status, ['confirmed', 'pending'], true)) || ($req->status === 'ready' && !$paid))
                    <div class="dr-step-note">
                        @if ($collect)
                            @if (!in_array($req->appointment?->status, ['confirmed', 'pending'], true))
                                @if ($req->appointment?->status === 'cancelled')
                                    Your pickup time was cancelled.
                                @endif
                                @if ($pickupSlots->isNotEmpty())
                                    <form method="POST" action="{{ route('portal.documents.pickup-appointment', $req) }}" class="dr-pickup-booking" data-pickup-form>
                                        @csrf
                                        <span class="dr-label">{{ $req->appointment?->status === 'cancelled' ? 'Choose a new pickup time' : 'Choose a pickup time' }}</span>
                                        @include('alumni.requests._pickup-picker', ['slots' => $pickupSlots, 'uid' => $req->id])
                                        <button type="submit" class="dr-btn dr-btn--primary dr-btn--sm" data-pp-submit disabled>Request pickup time</button>
                                    </form>
                                @else
                                    <span>No pickup times open. Check back later or contact the Registrar.</span>
                                @endif
                            @endif
                        @elseif ($req->status === 'ready')
                            Pickup is available after payment verification.
                        @endif
                    </div>
                    @endif

                    @if ($req->appointment && $req->appointment->slot)
                        <div class="dr-pickup-summary" aria-label="Pickup appointment">
                            <i data-lucide="calendar-days" aria-hidden="true"></i>
                            <span>
                                <strong>Pickup:</strong>
                                {{ \Carbon\Carbon::parse($req->appointment->slot->date)->format('M j, Y') }}
                                at {{ \Carbon\Carbon::parse($req->appointment->slot->start_time)->format('g:i A') }}
                                — {{ ucfirst($req->appointment->status) }}.
                                @if ($req->appointment->status === 'pending')
                                    @if ($collect)
                                        Awaiting confirmation.
                                    @else
                                        Slot reserved. Confirmed once your document is ready and paid.
                                    @endif
                                @elseif ($req->appointment->status === 'confirmed')
                                    Contact the Registrar to change or cancel this pickup time.
                                @elseif ($req->appointment->status === 'cancelled')
                                    {{ $collect && $pickupSlots->isNotEmpty() ? 'Choose a new pickup time above.' : 'Contact the Registrar for available pickup times.' }}
                                @endif
                            </span>
                        </div>
                    @endif

                    @if ($req->remarks)
                        <div class="dr-remark" style="margin-top:12px;">
                            <i data-lucide="message-square-warning" aria-hidden="true"></i>
                            <span><strong>Note from the Registrar:</strong> {{ $req->remarks }}</span>
                        </div>
                    @endif

                    <details class="dr-request-details">
                        <summary>Request details</summary>
                        @if ($req->purpose)<p class="dr-req-purpose">Purpose: {{ $req->purpose }}</p>@endif


                    </details>

                    <div class="dr-pay">
                        <div class="dr-pay-line"><span class="dtc-status-badge is-{{ $payTone[$req->payment_status] ?? 'neutral' }}">{{ $payLabel[$req->payment_status] ?? ucfirst(str_replace('_', ' ', $req->payment_status)) }}</span></div>
                        <div class="dr-actions"><a href="{{ route('portal.payments.document', $req) }}" class="dr-btn dr-btn--primary dr-btn--sm">Payment details</a></div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="dr-empty dr-empty--filter" id="drFilterEmpty" role="status">
            <p>No requests in this filter.</p>
        </div>

        @if (method_exists($requests, 'links'))
            <div style="margin-top:20px;">{{ $requests->links() }}</div>
        @endif
    @endif
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pickup-picker.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alumni-request-history.css') }}">
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

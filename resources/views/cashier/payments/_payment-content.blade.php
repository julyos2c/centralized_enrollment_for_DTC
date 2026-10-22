@php

    $fee = $latestPayment && $enrollment->is_paid
        ? $latestPayment->amount
        : \App\Models\Setting::get('enrollment_fee', 500);
@endphp

<div id="payment-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <x-dtc.user-avatar :user="$enrollment->user" class="dtc-review-avatar" />
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">{{ $enrollment->user->name }}</h5>
                <p class="dtc-review-email"><i data-lucide="mail"></i> {{ $enrollment->user->email }}</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            @if ($enrollment->is_paid)
                {{-- Paid state is already shown by the receipt action below; no badge needed. --}}
            @elseif ($latestPayment && $latestPayment->isGcash() && $latestPayment->isPending())
                <span class="dtc-status-badge is-warning"><span class="dtc-status-dot"></span> GCash Pending</span>
            @endif
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="dtc-review-body">

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="graduation-cap"></i> Payment Summary</span>
            </div>
            <div class="dtc-review-info-grid">
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Program</span>
                    <span class="dtc-review-info-value">{{ $enrollment->program->name }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Year / Semester</span>
                    <span class="dtc-review-info-value">Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Subjects Enrolled</span>
                    <span class="dtc-review-info-value">{{ count($enrollment->subject_ids) }} subjects</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Payment Type</span>
                    <span class="dtc-review-info-value">
                        @if ($latestPayment && $latestPayment->isGcash() && $latestPayment->isPending())
                            GCash
                        @elseif ($enrollment->is_paid && $latestPayment)
                            {{ $latestPayment->isWalkIn() ? 'Walk-in / Cash' : 'GCash' }}
                        @else
                            Walk-in / Cash
                        @endif
                    </span>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;
                        margin-top:14px; padding-top:14px; border-top:1px dashed var(--dtc-border);">
                <span class="dtc-review-info-label u-text-sm" >Enrollment Fee</span>
                <span style="font-size:22px; font-weight:800; color:#15803D;">
                    ₱{{ number_format($fee, 2) }}
                </span>
            </div>
        </div>

        @if (! $enrollment->is_paid)
            <div class="dtc-payment-info-notice">
                <i data-lucide="info" class="dtc-notice-icon" aria-hidden="true"></i>
                <span>Confirming payment will officially enroll the student.</span>
            </div>
        @endif

        @if (! $enrollment->is_paid && $latestPayment && $latestPayment->isPending() && $latestPayment->isGcash())
            {{-- ── GCash Review Branch ─────────────────────────────── --}}
            <div class="dtc-review-section">
                <div class="dtc-review-section-title">
                    <span><i data-lucide="qr-code"></i> GCash Payment Review</span>
                </div>
                <div class="dtc-review-info-grid">
                    <div class="dtc-review-info">
                        <span class="dtc-review-info-label">Reference No.</span>
                        <span class="dtc-review-info-value">{{ $latestPayment->reference_number }}</span>
                    </div>
                    <div class="dtc-review-info">
                        <span class="dtc-review-info-label">Submitted</span>
                        <span class="dtc-review-info-value">{{ $latestPayment->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                </div>
                <a href="{{ route('cashier.payments.proof', $latestPayment) }}" target="_blank"
                   class="dtc-btn dtc-btn-secondary" style="margin-top:12px;">
                    <i data-lucide="image"></i> View Proof of Payment
                </a>
            </div>

            {{-- Reject reason — hidden until "Reject" is clicked --}}
            <div class="dtc-review-reject-panel" id="rejectPanel-{{ $enrollment->id }}">
                <form method="POST" action="{{ route('cashier.payments.reject', $latestPayment) }}" id="rejectForm-{{ $enrollment->id }}">
                    @csrf
                    <label class="dtc-form-label" for="remarks-{{ $enrollment->id }}">
                        Rejection Reason <span class="dtc-required">*</span>
                    </label>
                    <textarea name="remarks" id="remarks-{{ $enrollment->id }}" class="dtc-form-control" rows="3" required
                              placeholder="e.g. Reference number not found in our GCash records."></textarea>
                    <p class="dtc-form-help">This message will be shared with the applicant.</p>
                </form>
            </div>

        @elseif (! $enrollment->is_paid && (! $latestPayment || $latestPayment->isRejected()))
            {{-- ── Walk-in Branch (also shown after a GCash rejection) ── --}}
            <form method="POST" action="{{ route('cashier.payments.store', $enrollment) }}" id="walkinForm-{{ $enrollment->id }}">
                @csrf
            </form>

        @elseif ($enrollment->is_paid)
            <div class="dtc-review-section">
                <button type="button" class="dtc-btn dtc-btn-secondary dtc-receipt-btn"
                        data-url="{{ route('cashier.payments.receipt', $enrollment) }}">
                    <i data-lucide="receipt"></i> View Receipt
                </button>
            </div>
        @endif

    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-footer">

        <div class="dtc-review-footer-default">
            <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Close</button>

            @if (! $enrollment->is_paid && $latestPayment && $latestPayment->isPending() && $latestPayment->isGcash())
                <div class="dtc-review-footer-right">
                    <button type="button" class="dtc-btn dtc-btn-outline-danger dtc-review-reject-trigger"
                            data-target="rejectPanel-{{ $enrollment->id }}">
                        <i data-lucide="x"></i> Reject
                    </button>
                    <form id="verify-gcash-{{ $latestPayment->id }}"
                          method="POST"
                          action="{{ route('cashier.payments.verify', $latestPayment) }}">
                        @csrf
                        <button type="button"
                                class="dtc-btn dtc-btn-success"
                                data-dtc-confirm
                                data-dtc-confirm-title="Verify GCash payment?"
                                data-dtc-confirm-message="The GCash payment from {subject} will be verified and the enrollment will be finalized."
                                data-dtc-confirm-subject="{{ $enrollment->user->name }}"
                                data-dtc-confirm-ok="Verify"
                                data-dtc-confirm-tone="success"
                                data-dtc-confirm-form="#verify-gcash-{{ $latestPayment->id }}">
                            <i data-lucide="check"></i> Verify Payment
                        </button>
                    </form>
                </div>
            @elseif (! $enrollment->is_paid && (! $latestPayment || $latestPayment->isRejected()))
                <div class="dtc-review-footer-right">
                    <button type="button"
                            class="dtc-btn dtc-btn-success"
                            data-dtc-confirm
                            data-dtc-confirm-title="Confirm cash payment?"
                            data-dtc-confirm-message="Confirm that cash payment has been received from {subject}. The enrollment will then be finalized."
                            data-dtc-confirm-subject="{{ $enrollment->user->name }}"
                            data-dtc-confirm-ok="Confirm"
                            data-dtc-confirm-tone="success"
                            data-dtc-confirm-form="#walkinForm-{{ $enrollment->id }}">
                        <i data-lucide="check"></i> Confirm Payment Received
                    </button>
                </div>
            @endif
        </div>

        <div class="dtc-review-footer-reject u-hidden" >
            <button type="button" class="dtc-btn dtc-btn-secondary dtc-review-reject-cancel">Cancel</button>
            <button type="button" class="dtc-btn dtc-btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Reject payment?" data-dtc-confirm-message="The submitted GCash payment from {subject} will be rejected with the reason entered above." data-dtc-confirm-subject="{{ $enrollment->user->name }}" data-dtc-confirm-ok="Reject" data-dtc-confirm-form="#rejectForm-{{ $enrollment->id }}">
                <i data-lucide="check"></i> Confirm Rejection
            </button>
        </div>

    </div>
</div>

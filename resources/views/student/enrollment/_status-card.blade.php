@php
    $payment = $enrollment->payment;
    $isApproved = $enrollment->status === 'approved';
    $isEnrolled = $isApproved && $enrollment->is_paid;
    $isRejected = $enrollment->status === 'rejected';
    $isEnrollmentReview = $enrollment->status === 'pending';
    $isPaymentReview = $isApproved && !$enrollment->is_paid && $payment && $payment->status === 'pending';
    $isPaymentRejected = $isApproved && !$enrollment->is_paid && $payment && $payment->status === 'rejected';
    $isPaymentDue = $isApproved && !$enrollment->is_paid && !$isPaymentReview;
    $state = $isEnrolled ? 'success' : ($isRejected ? 'danger' : (($isEnrollmentReview || $isPaymentReview) ? 'info' : ($isPaymentDue ? 'warning' : 'neutral')));
    $paymentStep = $isEnrolled ? 'done' : ($isApproved ? 'current' : 'upcoming');
    $enrolledStep = $isEnrolled ? 'done' : 'upcoming';
@endphp

<article class="card dtc-enrollment-status-card mb-3">
    <div class="dtc-enrollment-program-zone">
        <div class="dtc-enrollment-program">
            <span class="dtc-icon-swatch is-primary dtc-enrollment-program-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m2.5 9 9.5-5 9.5 5-9.5 5-9.5-5Z"></path>
                    <path d="M6.5 11.1v5.1c3.4 2.6 7.6 2.6 11 0v-5.1"></path>
                    <path d="M21.5 9v6"></path>
                </svg>
            </span>
            <div class="dtc-enrollment-program-copy">
                <span class="dtc-enrollment-eyebrow">Program</span>
                <h2>{{ $enrollment->program->name }}</h2>
            </div>
        </div>

        <span class="dtc-status-badge is-{{ $state }} dtc-enrollment-status-pill">
            <span class="dtc-status-dot" aria-hidden="true"></span>
            @if($isEnrolled)
                Enrolled
            @elseif($isPaymentRejected)
                Payment action needed
            @elseif($isPaymentReview)
                In review
            @elseif($isEnrollmentReview)
                In review
            @elseif($isPaymentDue)
                Payment due
            @elseif($isRejected)
                Rejected
            @else
                Status unavailable
            @endif
        </span>

        <div class="dtc-enrollment-chips" aria-label="Enrollment details">
            <span class="dtc-enrollment-chip">Year {{ $enrollment->year_level }}</span>
            <span class="dtc-enrollment-chip">Semester {{ $enrollment->semester }}</span>
            <span class="dtc-enrollment-chip">{{ count($enrollment->subject_ids ?? []) }} subjects</span>
        </div>
    </div>

    <div class="dtc-enrollment-progress-zone">
        <h3 class="dtc-enrollment-zone-label">Progress</h3>
        <ol class="dtc-enrollment-progress" aria-label="Enrollment progress">
            <li class="dtc-enrollment-progress-step is-done {{ $isEnrolled ? 'has-complete-connector' : '' }}">
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Submitted</strong>
                    <span>{{ $enrollment->created_at->format('M j, Y') }}</span>
                </span>
            </li>
            <li class="dtc-enrollment-progress-step is-{{ $paymentStep }} {{ $isEnrolled ? 'has-complete-connector' : '' }}" @if($paymentStep === 'current') aria-current="step" @endif>
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    @if($paymentStep === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                    @else
                        <span></span>
                    @endif
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Payment</strong>
                    <span>{{ $isEnrolled ? 'Confirmed' : ($isPaymentReview ? 'Verifying' : ($isPaymentRejected ? 'Action needed' : ($isApproved ? 'Awaiting' : 'Not yet'))) }}</span>
                </span>
            </li>
            <li class="dtc-enrollment-progress-step is-{{ $enrolledStep }}" @if($enrolledStep === 'done') aria-current="step" @endif>
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    @if($enrolledStep === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                    @else
                        <span></span>
                    @endif
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Enrolled</strong>
                    <span>{{ $isEnrolled ? 'This semester' : 'Not yet' }}</span>
                </span>
            </li>
        </ol>
    </div>

    <div class="dtc-enrollment-callout-zone">
        <div class="dtc-enrollment-callout is-{{ $state }} {{ $isEnrolled ? 'is-enrolled' : '' }}" @if($isRejected || $isPaymentRejected) role="alert" @else role="status" @endif>
            @unless($isEnrolled)
                <span class="dtc-icon-swatch dtc-enrollment-callout-icon" aria-hidden="true">
                    @if($isRejected || $isPaymentRejected)
                    <i data-lucide="circle-alert"></i>
                    @elseif($isEnrollmentReview || $isPaymentReview)
                    <i data-lucide="clock-3"></i>
                    @else
                    <i data-lucide="wallet"></i>
                    @endif
                </span>
            @endunless
            <div class="dtc-enrollment-callout-copy">
                @if($isEnrolled)
                    <strong>Payment confirmed</strong>
                    <span>You are officially enrolled for this semester.</span>
                @elseif($isRejected)
                    <strong>Enrollment needs attention</strong>
                    <span>{{ $enrollment->remarks ?: 'Contact the Registrar for guidance before submitting again.' }}</span>
                @elseif($isPaymentRejected)
                    <strong>Payment proof needs correction</strong>
                    <span>{{ $payment->rejection_reason ?: 'Review your payment details and submit corrected proof.' }}</span>
                @elseif($isEnrollmentReview)
                    <strong>Registrar review in progress</strong>
                    <span>We’ll notify you when the status changes.</span>
                @elseif($isPaymentReview)
                    <strong>Payment under review</strong>
                    <span>The Cashier is checking your payment. No action needed.</span>
                @elseif($isPaymentDue)
                    <strong>Payment needed</strong>
                    <span>Your enrollment is approved. Complete payment to finish enrollment.</span>
                @else
                    <strong>Status unavailable</strong>
                    <span>Please contact the Registrar for help with this enrollment.</span>
                @endif
            </div>

            @if($isEnrolled)
                <a href="{{ route('portal.cor.show', ['enrollment' => $enrollment->id]) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="file-text" aria-hidden="true"></i>
                    View Certificate of Registration
                </a>
            @elseif($isPaymentRejected)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                    Review and resubmit proof
                </a>
            @elseif($isPaymentDue)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="wallet" aria-hidden="true"></i>
                    Pay tuition balance
                </a>
            @elseif($isRejected)
                <a href="{{ route('portal.enrollment.create') }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                    Correct enrollment
                </a>
            @elseif($isPaymentReview && $payment->proof_of_payment)
                <a href="{{ route('portal.enrollment.payment.proof', $payment) }}" target="_blank" rel="noopener" class="dtc-enrollment-proof-link">
                    <i data-lucide="file-image" aria-hidden="true"></i>
                    View submitted proof
                    <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                    <span class="sr-only"> (opens in a new tab)</span>
                </a>
            @endif
        </div>
    </div>
</article>

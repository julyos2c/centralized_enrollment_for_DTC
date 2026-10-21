<div id="receipt-content" class="dtc-receipt">

    <div class="dtc-review-header dtc-receipt-header">
        <div class="dtc-review-identity">
            <div class="dtc-receipt-icon">
                <i data-lucide="receipt" aria-hidden="true"></i>
            </div>
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">Official Receipt</h5>
                <p class="dtc-review-email">Danao Technological College</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    <div class="dtc-review-body dtc-receipt-body" id="printable-receipt-modal">

        {{-- Receipt No + Date --}}
        <div style="display:flex; justify-content:space-between; align-items:center;
                    margin-bottom:20px; padding-bottom:14px; border-bottom:2px dashed var(--dtc-border);">
            <div>
                <span class="dtc-review-info-label">Receipt No.</span>
                <div class="dtc-receipt-number">
                    {{ $payment->receipt_no }}
                </div>
            </div>
            <div style="text-align:right;">
                <span class="dtc-review-info-label">Date Issued</span>
                <div class="dtc-receipt-date">
                    {{ optional($payment->paid_at)->format('M d, Y h:i A') ?? '—' }}
                </div>
            </div>
        </div>

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="user"></i> Student Information</span>
            </div>
            <div class="dtc-review-info-grid">
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Name</span>
                    <span class="dtc-review-info-value">{{ $enrollment->user->name }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Email</span>
                    <span class="dtc-review-info-value dtc-receipt-email">{{ $enrollment->user->email }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Program</span>
                    <span class="dtc-review-info-value">{{ $enrollment->program->name }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Year / Semester</span>
                    <span class="dtc-review-info-value">Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}</span>
                </div>
            </div>
        </div>

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="receipt"></i> Payment Details</span>
            </div>
            <div class="dtc-review-info-grid">
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Description</span>
                    <span class="dtc-review-info-value">Enrollment Fee</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Payment Method</span>
                    <span class="dtc-review-info-value">
                        @if ($payment->isWalkIn())
                            Walk-in / Cash
                        @else
                            GCash
                            @if ($payment->reference_number)
                                <span style="color:var(--dtc-text-secondary); font-size:11px;">/ Ref: {{ $payment->reference_number }}</span>
                            @endif
                        @endif
                    </span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Processed by</span>
                    <span class="dtc-review-info-value">{{ $payment->cashier->name ?? '—' }}</span>
                </div>
            </div>

            <div class="dtc-receipt-total">
                <span>
                    Total Amount Paid
                </span>
                <span class="dtc-receipt-total-amount">
                    ₱{{ number_format($payment->amount, 2) }}
                </span>
            </div>
        </div>

    </div>

    <div class="dtc-review-footer dtc-receipt-footer">
        <div class="dtc-review-footer-default">
            <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Close</button>
            <div class="dtc-review-footer-right">
                <button type="button" class="dtc-btn dtc-btn-primary" onclick="window.printReceiptModal()">
                    <i data-lucide="printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

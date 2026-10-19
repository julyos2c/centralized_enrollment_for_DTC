<div id="receipt-content">

    <div class="dtc-review-header" style="background:linear-gradient(135deg,#0F4CDB,#1a5feb);">
        <div class="dtc-review-identity" style="align-items:center;">
            <div style="width:44px; height:44px; background:rgba(255,255,255,0.2);
                        border-radius:12px; display:flex; align-items:center;
                        justify-content:center; flex-shrink:0;">
                <i data-lucide="receipt" style="font-size:18px; color:#FFC72C;"></i>
            </div>
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name" style="color:#fff;">Official Receipt</h5>
                <p class="dtc-review-email" style="color:rgba(255,255,255,0.8);">Danao Technological College</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    <div class="dtc-review-body" id="printable-receipt-modal">

        {{-- Receipt No + Date --}}
        <div style="display:flex; justify-content:space-between; align-items:center;
                    margin-bottom:20px; padding-bottom:14px; border-bottom:2px dashed var(--dtc-border);">
            <div>
                <span class="dtc-review-info-label">Receipt No.</span>
                <div style="font-size:17px; font-weight:800; color:#0F4CDB; margin-top:2px;">
                    {{ $payment->receipt_no }}
                </div>
            </div>
            <div style="text-align:right;">
                <span class="dtc-review-info-label">Date Issued</span>
                <div style="font-size:13px; font-weight:600; color:var(--dtc-text); margin-top:2px;">
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
                    <span class="dtc-review-info-value">{{ $enrollment->user->email }}</span>
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

            <div style="background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        border-radius:12px; padding:16px 18px;
                        display:flex; justify-content:space-between; align-items:center;
                        margin-top:14px;">
                <span style="font-size:13px; font-weight:600; color:rgba(255,255,255,0.85);">
                    Total Amount Paid
                </span>
                <span style="font-size:22px; font-weight:800; color:#FFC72C;">
                    ₱{{ number_format($payment->amount, 2) }}
                </span>
            </div>
        </div>

        <div class="dtc-receipt-confirmed">
            <i data-lucide="check-circle" style="color:#22C55E; margin-bottom:6px;"></i>
            <div class="dtc-receipt-confirmed-title">
                Payment Confirmed — Officially Enrolled
            </div>
            <div style="font-size:11.5px; color:var(--dtc-text-secondary); margin-top:2px;">
                This serves as your official receipt. Please keep this for your records.
            </div>
        </div>

    </div>

    <div class="dtc-review-footer">
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
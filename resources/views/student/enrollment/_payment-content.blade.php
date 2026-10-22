{{-- Shared "Enrollment Payment" content.
     Rendered both as the standalone payment-info page and, via AJAX,
     inside the lazy-loaded payment modal (see _payment-modal.blade.php). --}}

<div id="payment-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <div class="dtc-review-avatar">
                <i data-lucide="wallet"></i>
            </div>
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">Enrollment Payment</h5>
                <p class="dtc-review-email">
                    <i data-lucide="graduation-cap"></i>
                    {{ $enrollment->program->name }} — Year {{ $enrollment->year_level }}, {{ $enrollment->semester }} Semester
                </p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="dtc-review-body">

        {{-- Payment Status Block --}}
        @if ($latestPayment)
            @php
                $statusToneMap = [
                    'verified' => ['tone' => 'success', 'icon' => 'check-circle', 'label' => 'Payment Verified'],
                    'pending'  => ['tone' => 'warning', 'icon' => 'hourglass',     'label' => 'Payment Pending Verification'],
                    'rejected' => ['tone' => 'danger',  'icon' => 'x-circle',      'label' => 'Payment Rejected — Please resubmit below'],
                ];
                $statusKey = $latestPayment->isVerified() ? 'verified' : ($latestPayment->isPending() ? 'pending' : 'rejected');
                $s = $statusToneMap[$statusKey];
            @endphp
            <div class="dtc-review-section" data-pay-status="{{ $statusKey }}">
                <div class="card mb-0 border-{{ $s['tone'] === 'success' ? 'success' : ($s['tone'] === 'danger' ? 'danger' : 'warning') }}"
                         style="border-left-width:4px; border-left-style:solid;">
                    <div class="card-body d-flex align-items-center" style="gap:14px;">
                        <div class="dtc-icon-swatch is-{{ $s['tone'] }}" style="width:44px; height:44px; border-radius:12px; flex-shrink:0;">
                            <i data-lucide="{{ $s['icon'] }}" style="font-size:18px;"></i>
                        </div>
                        <div>
                            <div class="dtc-pay-status-label" style="font-weight:700; font-size:14px;">{{ $s['label'] }}</div>
                            @if ($latestPayment->isVerified())
                                <div  class="u-text-xxs-secondary">
                                    Receipt No: {{ $latestPayment->receipt_no }} &mdash; ₱{{ number_format($latestPayment->amount, 2) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @if ($statusKey === 'rejected')
                <div class="alert alert-danger mt-3" role="alert">
                    <strong>Cashier’s note:</strong> {{ $latestPayment->rejection_reason ?: 'The Cashier could not verify this proof. Check your payment details before submitting a corrected proof.' }}
                    <div class="mt-1"><strong>Next step:</strong> Submit corrected proof below. If you paid in person, contact the Cashier instead of uploading another proof.</div>
                </div>
            @elseif ($statusKey === 'pending')
                <p class="u-text-sm-secondary mt-2" role="status">Next step: wait for the Cashier to verify this proof. You don’t need to submit it again.</p>
            @endif
        @endif

        @if (! $enrollment->is_paid)
        {{-- Payment Options --}}
        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="coins"></i> Enrollment Fee</span>
                <span class="dtc-review-subject-count">₱{{ number_format($fee, 2) }}</span>
            </div>

            <div class="row">

                {{-- Walk-in Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header font-weight-bold">
                            <i data-lucide="building-2" class="mr-1"></i> Walk-in (Cashier)
                        </div>
                        <div class="card-body">
                            <p  class="u-text-sm-secondary-primary">
                                Pay in person at the Cashier's Office. Bring this confirmation and a valid ID.
                            </p>
                            <p style="font-size:12px; color:var(--dtc-text-muted); margin:0;">
                                No online action needed — the Cashier will record your payment.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- GCash Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header font-weight-bold">
                            <i data-lucide="smartphone" class="mr-1"></i> GCash
                        </div>
                        <div class="card-body">

                            <div class="dtc-gcash-mobile-note">
                                <i data-lucide="smartphone" aria-hidden="true"></i>
                                <div><strong>Paying on this phone?</strong> Use the GCash number below. You do not need to scan the QR.</div>
                            </div>

                            <div class="dtc-gcash-account">
                                <div>
                                    <span>GCash account</span>
                                    <strong>{{ $gcashName ?? 'Not configured' }}</strong>
                                    <code>{{ $gcashNumber ?? 'Not configured' }}</code>
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

                            @if ($gcashQrReady)
                                <img src="{{ route('portal.enrollment.gcash.qr') }}" alt="GCash QR Code"
                                     style="width:150px; height:150px; object-fit:contain; display:block; margin:0 auto 12px;
                                            border:1px solid var(--dtc-border); border-radius:10px;">
                            @else
                                <div style="width:150px; height:150px; display:flex; align-items:center; justify-content:center;
                                            margin:0 auto 12px; border:1px solid var(--dtc-border); border-radius:10px;
                                            background:var(--dtc-surface-soft); font-size:12px; color:var(--dtc-text-muted);">
                                    QR not yet configured
                                </div>
                            @endif

                            @if($gcashQrReady)
                                <a href="{{ route('portal.enrollment.gcash.qr', ['download' => 1]) }}" class="dtc-gcash-save-qr">
                                    <i data-lucide="download" aria-hidden="true"></i> Save QR to phone
                                </a>
                            @endif

                            <p class="dtc-gcash-proof-guide">After paying, enter the reference number and upload the successful transaction screenshot.</p>

                            @php $pendingExists = $latestPayment && $latestPayment->isPending(); @endphp

                            @if ($pendingExists)
                                <div class="alert alert-warning" style="font-size:12px; border-radius:8px; padding:10px 12px;">
                                    Your proof has been submitted and is awaiting Cashier review.
                                    <a href="{{ route('portal.enrollment.payment.proof', $latestPayment) }}" target="_blank"
                                       style="color:inherit; font-weight:600; text-decoration:underline;">
                                        View submitted proof
                                    </a>
                                </div>
                            @else
                                <form method="POST" action="{{ route('portal.enrollment.payment.gcash', $enrollment) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">GCash Reference Number</label>
                                        <input type="text" name="reference_number" value="{{ old('reference_number') }}"
                                            class="form-control @error('reference_number') is-invalid @enderror"
                                            placeholder="e.g. 1234567890" required>
                                        @error('reference_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-group mb-2">
                                        <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                            Proof of Payment (screenshot / PDF, max 5 MB)
                                        </label>
                                        <label class="dtc-file-label @error('proof_of_payment') is-invalid @enderror">
                                            <i data-lucide="upload" style="width:15px;height:15px;margin-right:6px;flex-shrink:0;"></i>
                                            <span class="dtc-file-text">Choose file…</span>
                                            <input type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf"
                                                class="dtc-file-input @error('proof_of_payment') is-invalid @enderror"
                                                required
                                                onchange="this.closest('label').querySelector('.dtc-file-text').textContent = this.files[0] ? this.files[0].name : 'Choose file…'">
                                        </label>
                                        @error('proof_of_payment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        Submit GCash Proof
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @endif

    </div>

</div>

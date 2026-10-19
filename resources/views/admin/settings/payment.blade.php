@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Settings</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Payment</li>
                </ol>
            </nav>
        </div>

        <button type="submit" form="payment-settings-form" class="btn btn-primary">
            <i data-lucide="save" class="mr-1"></i> Save Changes
        </button>
    </div>
@endsection

@section('content')
<div class="row">

    {{-- Payment Settings panels --}}
    <div class="col-12">

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="row">
            {{-- Fee & GCash Details --}}
            <div class="col-lg-7 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="settings-section-title">
                            <div class="settings-section-icon is-primary">
                                <i data-lucide="banknote"></i>
                            </div>
                            <h3>Fee &amp; GCash Details</h3>
                        </div>

                        <form id="payment-settings-form" method="POST" action="{{ route('admin.settings.payment.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="form-group">
                                <label>Enrollment Fee (&#8369;)</label>
                                <input type="number"
                                       name="enrollment_fee"
                                       step="0.01"
                                       min="0"
                                       value="{{ old('enrollment_fee', $settings->enrollment_fee ?? 500) }}"
                                       class="form-control"
                                       required>
                            </div>

                            <hr>
                            <h5 class="font-weight-bold mb-3">Document Request Fees</h5>
                            <p class="u-text-secondary-sm">Set the fee per copy. These Admin-managed rates apply to new alumni requests; existing requests keep their recorded fees. The Cashier records payment and the Registrar manages document status.</p>
                            @foreach ([
                                'tor' => ['Transcript of Records', 50],
                                'diploma' => ['Diploma', 100],
                                'certification' => ['Certification', 30],
                                'true_copy' => ['True Copy of Records', 50],
                            ] as $type => [$label, $defaultFee])
                                <div class="form-group">
                                    <label for="document_fee_{{ $type }}">{{ $label }} fee (&#8369; per copy)</label>
                                    <input id="document_fee_{{ $type }}" type="number" name="document_fee_{{ $type }}"
                                           step="0.01" min="0" max="999999.99"
                                           value="{{ old('document_fee_' . $type, $settings->{'document_fee_' . $type} ?? $defaultFee) }}"
                                           class="form-control" required>
                                </div>
                            @endforeach

                            <div class="form-group">
                                <label>GCash Account Name</label>
                                <input type="text"
                                       name="gcash_name"
                                       value="{{ old('gcash_name', $settings->gcash_name ?? '') }}"
                                       class="form-control"
                                       placeholder="e.g. DTC Cashier">
                            </div>

                            <div class="form-group">
                                <label>GCash Number</label>
                                <input type="text"
                                       name="gcash_number"
                                       value="{{ old('gcash_number', $settings->gcash_number ?? '') }}"
                                       class="form-control"
                                       placeholder="e.g. 09XXXXXXXXX"
                                       maxlength="11">
                            </div>

                            <div class="form-group mb-0">
                                <label>Payment Deadline (days after approval)</label>
                                <input type="number"
                                       name="payment_deadline"
                                       min="1"
                                       value="{{ old('payment_deadline', $settings->payment_deadline ?? '') }}"
                                       class="form-control"
                                       placeholder="e.g. 7">
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- GCash QR Code --}}
            <div class="col-lg-5 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="settings-section-title">
                            <div class="settings-section-icon is-warning">
                                <i data-lucide="qr-code"></i>
                            </div>
                            <h3>GCash QR Code</h3>
                        </div>

                        @php
                            $qrPath = $settings->gcash_qr_path ?? null;
                            $qrExists = $qrPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($qrPath);
                        @endphp

                        @if ($qrExists)
                            <div class="mb-3">
                                <p style="font-size:12px; color:var(--dtc-text-secondary); margin-bottom:8px;">Current QR Code:</p>
                                <img src="{{ route('admin.settings.payment.qr.preview') }}"
                                     alt="GCash QR Code"
                                     style="width:160px; height:160px; object-fit:contain;
                                            border:1.5px solid var(--dtc-border); border-radius:12px; padding:8px;">
                            </div>
                        @else
                            <div style="background:#FEF9C3; border:1.5px solid #FDE68A; border-radius:8px;
                                        padding:12px 16px; margin-bottom:16px; font-size:13px; color:#92400E;">
                                <i data-lucide="alert-triangle" class="mr-2"></i>
                                No QR code uploaded yet. Students will see a placeholder on the payment page.
                            </div>
                        @endif

                        <form method="POST"
                              action="{{ route('admin.settings.payment.qr') }}"
                              enctype="multipart/form-data">
                            @csrf

                            <div class="form-group">
                                <label>Upload New QR Image</label>
                                <div class="u-text-xs-hint">PNG or JPG, max 2 MB.</div>
                                <input type="file"
                                       name="gcash_qr"
                                       accept=".jpg,.jpeg,.png"
                                       class="form-control-file"
                                       required>
                                @error('gcash_qr')
                                    <p style="color:#DC2626; font-size:12px; margin-top:4px;">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-dark">
                                <i data-lucide="upload" class="mr-1"></i> Upload QR Code
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

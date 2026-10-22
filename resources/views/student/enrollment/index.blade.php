@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Enrollment Status')

@section('content_header')
    <div class="dtc-enrollment-index-header">
        <div class="dtc-enrollment-index-heading">
            <h1>Enrollment Status</h1>
            <p>Your current enrollment and payment progress.</p>
        </div>
    </div>
@endsection

@section('content')

@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

@if ($enrollments->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-lucide="file-text" class="mb-3 u-border-color" style="width:4em;height:4em"></i>
            <h5 style="color:var(--dtc-text); font-weight:700;">No Enrollments Yet</h5>
            <p style="color:var(--dtc-text-secondary); font-size:13px; max-width:360px; margin:0 auto 20px;">
                You haven't submitted any enrollment yet.
            </p>
            <a href="{{ route('portal.enrollment.create') }}" class="btn btn-primary">
                <i data-lucide="plus" class="mr-1"></i> Enroll Now
            </a>
        </div>
    </div>
@else
    @foreach ($enrollments as $enrollment)
    <div class="card mb-3 dtc-enrollment-status-card"
         style="border-left:4px solid
            {{ $enrollment->is_paid ? 'var(--dtc-success)' :
               ($enrollment->status === 'approved' ? 'var(--dtc-accent)' :
               ($enrollment->status === 'pending' ? 'var(--dtc-primary)' : 'var(--dtc-danger)')) }};">
        <div class="card-body dtc-enrollment-status-body">
            <div class="row align-items-center dtc-enrollment-status-summary">

                {{-- Status Icon --}}
                @php
                    $payment = $enrollment->payment;
                    $tone = $enrollment->is_paid ? 'success' : ($enrollment->status === 'rejected' || ($payment && $payment->status === 'rejected') ? 'danger' : ($enrollment->status === 'approved' ? 'warning' : 'info'));
                @endphp
                <div class="col-auto dtc-enrollment-status-icon">
                    <div class="dtc-icon-swatch is-{{ $tone }}" style="width:52px; height:52px; border-radius:14px;">
                        <i data-lucide="{{ $enrollment->is_paid ? 'check-check' :
                                        ($enrollment->status === 'approved' ? 'clock' :
                                        ($enrollment->status === 'pending' ? 'hourglass' : 'x')) }}" style="font-size:20px;"></i>
                    </div>
                </div>

                {{-- Details --}}
                <div class="col dtc-enrollment-status-details">
                    <div style="font-size:15px; font-weight:700; color:var(--dtc-text);">
                        {{ $enrollment->program->name }}
                    </div>
                    <div style="font-size:13px; color:var(--dtc-text-secondary); margin-top:2px;">
                        Year {{ $enrollment->year_level }} — Semester {{ $enrollment->semester }} •
                        {{ count($enrollment->subject_ids) }} subjects •
                        Submitted {{ $enrollment->created_at->format('M d, Y') }}
                    </div>
                </div>

                {{-- Status Badge --}}
                <div class="col-auto dtc-enrollment-status-badge">
                    @if($enrollment->is_paid)
                        <span class="dtc-status-badge is-success" style="font-size:12px; font-weight:700; padding:6px 14px;">
                            <i data-lucide="check-circle" class="mr-1"></i> Enrolled
                        </span>
                    @elseif($enrollment->status === 'approved' && $payment && $payment->status === 'pending')
                        <span class="dtc-status-badge is-info" style="font-size:12px; font-weight:700; padding:6px 14px;">
                            <i data-lucide="hourglass" class="mr-1"></i> Payment Under Review
                        </span>
                    @elseif($enrollment->status === 'approved' && $payment && $payment->status === 'rejected')
                        <span class="dtc-status-badge is-danger" style="font-size:12px; font-weight:700; padding:6px 14px;">
                            <i data-lucide="circle-alert" class="mr-1"></i> Payment Proof Rejected
                        </span>
                    @elseif($enrollment->status === 'approved')
                        <span class="dtc-status-badge is-warning" style="font-size:12px; font-weight:700; padding:6px 14px;">
                            <i data-lucide="clock" class="mr-1"></i> Awaiting Payment
                        </span>
                    @elseif($enrollment->status === 'pending')
                        <span class="dtc-status-badge is-info" style="font-size:12px; font-weight:700; padding:6px 14px;">
                            <i data-lucide="hourglass" class="mr-1"></i> Under Review
                        </span>
                    @else
                        <span class="dtc-status-badge is-danger" style="font-size:12px; font-weight:700; padding:6px 14px;">
                            <i data-lucide="x-circle" class="mr-1"></i> Rejected
                        </span>
                    @endif
                </div>

                {{-- Action --}}
                @if($enrollment->status === 'approved' && !$enrollment->is_paid && (!$payment || $payment->status === 'rejected'))
                    <div class="col-auto dtc-enrollment-status-action">
                        <button type="button" class="btn btn-warning btn-sm dtc-payment-btn"
                                data-url="{{ route('portal.enrollment.payment-info', $enrollment) }}">
                            {{ $payment && $payment->status === 'rejected' ? 'Review and Resubmit Proof' : 'Payment Details' }} →
                        </button>
                    </div>
                @endif
            </div>

            {{-- Rejection Remarks --}}
            @if($enrollment->status === 'rejected' && $enrollment->remarks)
            <div class="alert alert-danger" style="margin-top:14px; padding:12px 16px; font-size:13px;">
                <i data-lucide="alert-circle" class="mr-2"></i>
                <strong>Reason:</strong> {{ $enrollment->remarks }}
            </div>
            @endif

            @if($enrollment->status === 'pending')
                <div class="alert alert-info dtc-enrollment-next-step" role="status">
                    <strong>Registrar review in progress.</strong> We’ll notify you when the status changes.
                </div>
            @elseif($enrollment->status === 'rejected')
                <div class="alert alert-warning dtc-enrollment-next-step" role="status">
                    <strong>Next step:</strong> Review the Registrar’s reason above, then contact the Registrar or submit a corrected enrollment.
                    <a href="{{ route('portal.enrollment.create') }}" class="alert-link ml-1">Start a corrected enrollment</a>.
                </div>
            @elseif($enrollment->status === 'approved' && !$enrollment->is_paid && $payment && $payment->status === 'pending')
                <div class="alert alert-info dtc-enrollment-next-step" role="status">
                    <strong>Payment verification in progress.</strong> Please wait before submitting another proof.
                    @if($payment->proof_of_payment)
                        <a href="{{ route('portal.enrollment.payment.proof', $payment) }}" target="_blank" rel="noopener" class="alert-link ml-1">View submitted proof</a>.
                    @endif
                </div>
            @elseif($enrollment->status === 'approved' && !$enrollment->is_paid && $payment && $payment->status === 'rejected')
                <div class="alert alert-warning dtc-enrollment-next-step" role="alert">
                    <strong>Payment proof needs correction:</strong> {{ $payment->rejection_reason ?: 'The Cashier could not verify the proof. Review your details and contact the Cashier if you need help.' }}
                    <div class="mt-1"><strong>Next step:</strong> Open Payment Details and submit corrected proof.</div>
                </div>
            @elseif($enrollment->status === 'approved' && !$enrollment->is_paid)
                <div class="alert alert-warning dtc-enrollment-next-step" role="status">
                    <strong>Next step:</strong> Pay the approved enrollment fee at the Cashier or open Payment Details to submit GCash proof.
                </div>
            @endif

            {{-- Payment Success --}}
            @if($enrollment->is_paid)
            <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap u-gap-10" style="margin-top:14px; padding:12px 16px; font-size:13px;">
                <span><i data-lucide="check-circle" class="mr-2"></i>Payment confirmed. You are officially enrolled for this semester.</span>
                <a href="{{ route('portal.cor.show') }}" class="btn btn-sm btn-outline-success">
                    <i data-lucide="file-text" class="mr-1"></i> View Certificate of Registration
                </a>
            </div>
            @endif
        </div>
    </div>
    @endforeach

    @if ($enrollments->hasPages())
    <div class="d-flex justify-content-between align-items-center flex-wrap mt-3 u-gap-15" >
        <small  class="u-text-secondary">
            Showing
            <strong>{{ $enrollments->firstItem() }}</strong>
            to
            <strong>{{ $enrollments->lastItem() }}</strong>
            of
            <strong>{{ $enrollments->total() }}</strong>
            results
        </small>
        <div>
            {{ $enrollments->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
    </div>
    @endif
@endif

@include('student.enrollment._payment-modal')

@endsection

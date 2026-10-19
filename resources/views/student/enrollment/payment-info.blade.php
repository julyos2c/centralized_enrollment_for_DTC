@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Payment Information')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Payment Information</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
            Your enrollment has been approved — complete your payment to finalize
        </p>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">

        {{-- Approved Badge --}}
        <div style="background:#F0FDF4; border:1.5px solid #BBF7D0; border-radius:16px;
                    padding:20px 24px; margin-bottom:20px; display:flex;
                    align-items:center; gap:16px;">
            <div style="width:48px; height:48px; border-radius:14px; background:#DCFCE7;
                        display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-check-circle" style="font-size:22px; color:#15803D;"></i>
            </div>
            <div>
                <div style="font-size:14px; font-weight:700; color:#15803D;">
                    Enrollment Approved!
                </div>
                <div style="font-size:13px; color:#166534; margin-top:2px;">
                    The Registrar has approved your enrollment.
                    Please proceed to the Cashier's Office to pay your enrollment fee.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                Enrollment Details
            </div>
            <div class="card-body">
                <table style="width:100%; font-size:13px;">
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Program</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            {{ $enrollment->program->name }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Year Level</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            Year {{ $enrollment->year_level }} — Semester {{ $enrollment->semester }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Subjects</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            {{ count($enrollment->subject_ids) }} subjects enrolled
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 0; font-size:15px; font-weight:700; color:#1E293B;">
                            Enrollment Fee
                        </td>
                        <td style="padding:16px 0; font-size:26px; font-weight:800;
                                   color:#0F4CDB; text-align:right;">
                            ₱500.00
                        </td>
                    </tr>
                </table>

                <div style="background:#FFFBEB; border:1.5px solid #FDE68A; border-radius:12px;
                            padding:16px; margin-top:4px;">
                    <div style="font-size:13px; font-weight:700; color:#92400E; margin-bottom:8px;">
                        <i class="fas fa-map-marker-alt mr-2"></i> Payment Instructions
                    </div>
                    <div style="font-size:13px; color:#B45309; line-height:1.7;">
                        Please go to the <strong>Cashier's Office</strong> at the DTC Main Building
                        and pay the enrollment fee of <strong>₱500.00</strong>.
                        Bring a valid ID. An official receipt will be issued upon payment.
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('portal.enrollment.index') }}"
           class="btn btn-secondary btn-block mt-3">
            ← Back to My Enrollments
        </a>
    </div>
</div>
@endsection
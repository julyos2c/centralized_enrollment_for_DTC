@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Process Payment</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Confirm and record enrollment fee collection
            </p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Student Info Card --}}
        <div class="card mb-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:56px; height:56px; border-radius:50%;
                                background:rgba(255,255,255,0.2); display:flex;
                                align-items:center; justify-content:center;
                                color:#fff; font-weight:800; font-size:22px; flex-shrink:0;
                                border:2px solid rgba(255,255,255,0.3);">
                        {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#fff;">
                            {{ $enrollment->user->name }}
                        </div>
                        <div style="font-size:13px; color:rgba(255,255,255,0.75);">
                            {{ $enrollment->user->email }}
                        </div>
                        <div style="font-size:12px; color:#FFC72C; margin-top:4px; font-weight:600;">
                            {{ $enrollment->program->name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                Payment Summary
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
                        <td style="padding:12px 0; color:#64748B;">Year / Semester</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Subjects Enrolled</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            {{ count($enrollment->subject_ids) }} subjects
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Payment Type</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            Walk-in / Cash
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 0; font-size:16px; font-weight:700; color:#1E293B;">
                            Enrollment Fee
                        </td>
                        <td style="padding:16px 0; font-size:24px; font-weight:800;
                                   color:#15803D; text-align:right;">
                            ₱500.00
                        </td>
                    </tr>
                </table>

                <div style="background:#F0FDF4; border:1.5px solid #BBF7D0; border-radius:12px;
                            padding:14px 16px; margin-bottom:20px; font-size:13px; color:#15803D;">
                    <i class="fas fa-info-circle mr-2"></i>
                    Upon confirmation, the student's account will be upgraded to
                    <strong>Student</strong> status and they will be officially enrolled.
                </div>

                <form method="POST" action="{{ route('cashier.payments.store', $enrollment) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-block"
                            style="font-size:15px; padding:14px;"
                            onclick="return confirm('Confirm payment of ₱500.00 received from {{ $enrollment->user->name }}?')">
                        <i class="fas fa-check-circle mr-2"></i>
                        Confirm Payment Received — ₱500.00
                    </button>
                </form>

                <a href="{{ route('cashier.payments.index') }}"
                   class="btn btn-secondary btn-block mt-2">Cancel</a>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Enrollment Status')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">My Enrollment Status</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Track your enrollment submissions and payment status
            </p>
        </div>
        <a href="{{ route('portal.enrollment.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> New Enrollment
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($enrollments->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-file-alt fa-4x mb-3" style="color:#E2E8F0;"></i>
            <h5 style="color:#1E293B; font-weight:700;">No Enrollments Yet</h5>
            <p style="color:#64748B; font-size:13px; max-width:360px; margin:0 auto 20px;">
                You haven't submitted any enrollment yet.
            </p>
            <a href="{{ route('portal.enrollment.create') }}" class="btn btn-primary">
                <i class="fas fa-plus mr-1"></i> Enroll Now
            </a>
        </div>
    </div>
@else
    @foreach ($enrollments as $enrollment)
    <div class="card mb-3"
         style="border-left:4px solid
            {{ $enrollment->is_paid ? '#22C55E' :
               ($enrollment->status === 'approved' ? '#FFC72C' :
               ($enrollment->status === 'pending' ? '#3B82F6' : '#EF4444')) }};">
        <div class="card-body">
            <div class="row align-items-center">

                {{-- Status Icon --}}
                <div class="col-auto">
                    <div style="width:52px; height:52px; border-radius:14px; display:flex;
                                align-items:center; justify-content:center;
                                background:{{ $enrollment->is_paid ? '#DCFCE7' :
                                              ($enrollment->status === 'approved' ? '#FEF9C3' :
                                              ($enrollment->status === 'pending' ? '#DBEAFE' : '#FEE2E2')) }};">
                        <i class="fas {{ $enrollment->is_paid ? 'fa-check-double' :
                                        ($enrollment->status === 'approved' ? 'fa-clock' :
                                        ($enrollment->status === 'pending' ? 'fa-hourglass-half' : 'fa-times')) }}"
                           style="font-size:20px;
                                  color:{{ $enrollment->is_paid ? '#15803D' :
                                           ($enrollment->status === 'approved' ? '#D97706' :
                                           ($enrollment->status === 'pending' ? '#1D4ED8' : '#DC2626')) }};"></i>
                    </div>
                </div>

                {{-- Details --}}
                <div class="col">
                    <div style="font-size:15px; font-weight:700; color:#1E293B;">
                        {{ $enrollment->program->name }}
                    </div>
                    <div style="font-size:13px; color:#64748B; margin-top:2px;">
                        Year {{ $enrollment->year_level }} — Semester {{ $enrollment->semester }} •
                        {{ count($enrollment->subject_ids) }} subjects •
                        Submitted {{ $enrollment->created_at->format('M d, Y') }}
                    </div>
                </div>

                {{-- Status Badge --}}
                <div class="col-auto">
                    @if($enrollment->is_paid)
                        <span style="background:#DCFCE7; color:#15803D; font-size:12px;
                                     font-weight:700; padding:6px 14px; border-radius:20px;">
                            <i class="fas fa-check-circle mr-1"></i> Enrolled
                        </span>
                    @elseif($enrollment->status === 'approved')
                        <span style="background:#FEF9C3; color:#A16207; font-size:12px;
                                     font-weight:700; padding:6px 14px; border-radius:20px;">
                            <i class="fas fa-clock mr-1"></i> Awaiting Payment
                        </span>
                    @elseif($enrollment->status === 'pending')
                        <span style="background:#DBEAFE; color:#1D4ED8; font-size:12px;
                                     font-weight:700; padding:6px 14px; border-radius:20px;">
                            <i class="fas fa-hourglass-half mr-1"></i> Under Review
                        </span>
                    @else
                        <span style="background:#FEE2E2; color:#DC2626; font-size:12px;
                                     font-weight:700; padding:6px 14px; border-radius:20px;">
                            <i class="fas fa-times-circle mr-1"></i> Rejected
                        </span>
                    @endif
                </div>

                {{-- Action --}}
                <div class="col-auto">
                    @if($enrollment->status === 'approved' && !$enrollment->is_paid)
                        <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}"
                           class="btn btn-warning btn-sm">
                            Pay Now →
                        </a>
                    @endif
                </div>
            </div>

            {{-- Rejection Remarks --}}
            @if($enrollment->status === 'rejected' && $enrollment->remarks)
            <div style="margin-top:14px; background:#FEF2F2; border-radius:10px;
                        padding:12px 16px; font-size:13px; color:#DC2626;">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <strong>Reason:</strong> {{ $enrollment->remarks }}
            </div>
            @endif

            {{-- Payment Success --}}
            @if($enrollment->is_paid)
            <div style="margin-top:14px; background:#F0FDF4; border-radius:10px;
                        padding:12px 16px; font-size:13px; color:#15803D;">
                <i class="fas fa-check-circle mr-2"></i>
                Payment confirmed. You are officially enrolled for this semester.
            </div>
            @endif
        </div>
    </div>
    @endforeach
@endif

@endsection
@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payments')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Process Payments</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
            Approved enrollments awaiting payment collection
        </p>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($enrollments->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-4x mb-3" style="color:#E2E8F0;"></i>
            <h5 style="color:#1E293B; font-weight:700;">All Payments Processed</h5>
            <p style="color:#64748B; font-size:13px;">
                No approved enrollments awaiting payment at the moment.
            </p>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Year / Semester</th>
                        <th>Subjects</th>
                        <th>Fee</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:38px; height:38px; border-radius:50%;
                                            background:linear-gradient(135deg,#22C55E,#059669);
                                            display:flex; align-items:center; justify-content:center;
                                            color:#fff; font-weight:700; font-size:14px; flex-shrink:0;">
                                    {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                        {{ $enrollment->user->name }}
                                    </div>
                                    <div style="font-size:11px; color:#64748B;">
                                        {{ $enrollment->user->email }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:13px; color:#1E293B; font-weight:500;">
                            {{ $enrollment->program->name }}
                        </td>
                        <td style="font-size:13px; color:#64748B;">
                            Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}
                        </td>
                        <td>
                            <span style="background:#EEF2FF; color:#0F4CDB; font-size:12px;
                                         font-weight:600; padding:3px 10px; border-radius:20px;">
                                {{ count($enrollment->subject_ids) }} subjects
                            </span>
                        </td>
                        <td>
                            <span style="font-size:14px; font-weight:800; color:#15803D;">
                                ₱500.00
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('cashier.payments.show', $enrollment) }}"
                               style="background:#DCFCE7; color:#15803D; padding:6px 14px;
                                      border-radius:8px; font-size:12px; font-weight:600;
                                      text-decoration:none;">
                                <i class="fas fa-money-bill-wave mr-1"></i> Process
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
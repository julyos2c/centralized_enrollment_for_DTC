@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Document Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">My Document Requests</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Track your document requests and pickup status
            </p>
        </div>
        <a href="{{ route('portal.documents.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Request Document
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- STAT CARDS --}}
<div class="row mb-3">
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-blue">
            <div class="stat-icon"><i class="fas fa-folder"></i></div>
            <div class="stat-value">{{ $totalRequests }}</div>
            <div class="stat-label">Total Requests</div>
            <div class="stat-footer">This Year</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-purple">
            <div class="stat-icon"><i class="fas fa-box-open"></i></div>
            <div class="stat-value">{{ $readyRequests }}</div>
            <div class="stat-label">Ready for Pickup</div>
            <div class="stat-footer">Documents Available</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-green">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-value">{{ $releasedDocs }}</div>
            <div class="stat-label">Released</div>
            <div class="stat-footer">This Year</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-yellow">
            <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-value" style="font-size:20px;">₱{{ number_format($totalFees, 2) }}</div>
            <div class="stat-label">Total Fees</div>
            <div class="stat-footer">All Requests</div>
        </div>
    </div>
</div>

@if ($requests->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-4x mb-3" style="color:#E2E8F0;"></i>
            <h5 style="color:#1E293B; font-weight:700;">No Document Requests Yet</h5>
            <p style="color:#64748B; font-size:13px; max-width:360px; margin:0 auto 20px;">
                Submit a document request to get your TOR, Diploma, Certification, or other records.
            </p>
            <a href="{{ route('portal.documents.create') }}" class="btn btn-primary">
                <i class="fas fa-plus mr-1"></i> Request Document
            </a>
        </div>
    </div>
@else
    {{-- Request Timeline --}}
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card">
                <div class="card-header font-weight-bold" style="color:#1E293B;">
                    Request History
                </div>
                <div class="card-body p-0">
                    @foreach ($requests as $req)
                    <div style="padding:18px 20px; border-bottom:1px solid #F1F5F9;
                                display:flex; align-items:center; gap:16px;">

                        {{-- Status dot --}}
                        <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                    background:{{ $req->status === 'released' ? '#DCFCE7' :
                                                  ($req->status === 'ready' ? '#EDE9FE' :
                                                  ($req->status === 'processing' ? '#DBEAFE' : '#FEF9C3')) }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i class="fas {{ $req->status === 'released' ? 'fa-check-double' :
                                           ($req->status === 'ready' ? 'fa-box-open' :
                                           ($req->status === 'processing' ? 'fa-spinner' : 'fa-clock')) }}"
                               style="font-size:16px;
                                      color:{{ $req->status_color }};"></i>
                        </div>

                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:14px; font-weight:600; color:#1E293B;">
                                    {{ $req->document_label }}
                                </div>
                                <span style="font-size:11px; font-weight:700; padding:3px 10px;
                                             border-radius:20px;
                                             background:{{ $req->status === 'released' ? '#DCFCE7' :
                                                           ($req->status === 'ready' ? '#EDE9FE' :
                                                           ($req->status === 'processing' ? '#DBEAFE' : '#FEF9C3')) }};
                                             color:{{ $req->status_color }};">
                                    {{ ucfirst($req->status) }}
                                </span>
                            </div>
                            <div style="font-size:12px; color:#64748B; margin-top:4px;">
                                {{ $req->copies }} cop{{ $req->copies > 1 ? 'ies' : 'y' }} •
                                Fee: ₱{{ number_format($req->fee, 2) }} •
                                {{ $req->created_at->format('M d, Y') }}
                            </div>
                            @if($req->purpose)
                            <div style="font-size:12px; color:#94A3B8; margin-top:2px;">
                                {{ $req->purpose }}
                            </div>
                            @endif
                            @if($req->remarks)
                            <div style="font-size:12px; color:#DC2626; margin-top:4px;
                                        background:#FEF2F2; padding:6px 10px; border-radius:8px;">
                                <i class="fas fa-info-circle mr-1"></i>{{ $req->remarks }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="col-lg-4 mb-3">
            <div class="card">
                <div class="card-header font-weight-bold" style="color:#1E293B;">Quick Actions</div>
                <div class="card-body p-3">
                    <a href="{{ route('portal.documents.create') }}" class="quick-action-btn">
                        <i class="fas fa-plus"></i> Request Document
                    </a>
                    <a href="{{ route('portal.appointments.create') }}" class="quick-action-btn">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="{{ route('portal.appointments.index') }}" class="quick-action-btn">
                        <i class="fas fa-calendar"></i> My Appointments
                    </a>
                    <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection
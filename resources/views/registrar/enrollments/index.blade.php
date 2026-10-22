@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Pending Enrollments')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text" >Pending Enrollments</h4>
        <p class="mb-0 u-text-secondary-sm" >
            Enrollment submissions awaiting registrar approval.
        </p>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace">
<form method="GET" action="{{ route('registrar.enrollments.index') }}" class="dtc-fl" aria-label="Search and filter pending enrollments">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}" placeholder="Search student or program" aria-label="Search pending enrollments">
        </div>
        <button type="button" class="dtc-fl-toggle" data-toggle="collapse" data-target="#enrollmentFilters" aria-expanded="false" aria-controls="enrollmentFilters">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i><span>Filters</span>
        </button>
        <button type="submit" class="sr-only">Search enrollments</button>
    </div>
    <div class="collapse" id="enrollmentFilters">
        <div class="dtc-fl-panel">
            @include('partials.table-rows', ['tableRecords' => $enrollments, 'tableId' => 'registrar-table-1', 'fixedRows' => true])
            <div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('registrar.enrollments.index') }}" class="dtc-fl-clear">Reset</a>
            </div>
        </div>
    </div>
</form>




{{-- Enrollments Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text" >All Pending Enrollments</span>
        <span  class="u-text-secondary-sm">{{ $enrollments->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
<table id="registrar-table-1" class="rg-record-table table mb-0">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>Year / Semester</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($enrollments as $enrollment)
                <tr>
                    <td data-reg-label="Student Name">
                        <div  class="u-flex-center-gap-10">
                            <x-dtc.user-avatar :user="$enrollment->user" style="width:32px; height:32px; border-radius:50%;
                                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;" />
                            <span style="font-size:13px; font-weight:500;">{{ $enrollment->user->name }}</span>
                        </div>
                    </td>
                    <td data-reg-label="Program">
                        <div style="font-size:13px; font-weight:500; color:var(--dtc-text);">{{ $enrollment->program->name }}</div>
                        <div  class="u-text-xs-muted">{{ $enrollment->program->code }}</div>
                    </td>
                    <td data-reg-label="Year / Semester"  class="u-text-secondary-sm">
                        {{ $enrollment->year_level }}{{ ['st','nd','rd','th'][$enrollment->year_level - 1] ?? 'th' }} Year
                        <span  class="u-text-muted">&middot; Sem {{ $enrollment->semester }}</span>
                    </td>
                    <td data-reg-label="Type">
                        @php $classification = $enrollment->resolveClassification(); @endphp
                        @if($classification !== 'new_student')
                            <span class="dtc-status-badge is-info" style="font-size:10px;">{{ ucfirst(str_replace('_', ' ', $classification)) }}</span>
                        @endif
                        @if($enrollment->is_irregular)
                            <span class="dtc-status-badge is-warning" style="font-size:10px;">Irregular</span>
                        @endif
                        @if($classification === 'new_student' && !$enrollment->is_irregular)
                            <span class="u-text-muted" style="font-size:11px;">Regular</span>
                        @endif
                    </td>
                    <td data-reg-label="Status">
                        <x-dtc.status-badge :status="$enrollment->status" />
                    </td>

                    <td data-reg-label="Submitted"  class="u-text-secondary-sm">
                        {{ $enrollment->created_at->format('M d, Y h:i A') }}
                    </td>
                    <td data-reg-label="Action">
                        <button type="button" class="dtc-review-btn" data-url="{{ route('registrar.enrollments.show', $enrollment) }}">
                            <i data-lucide="eye"></i> Review
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 u-text-muted" >
                        No pending enrollments.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="card-footer u-panel-footer" >
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
            <small  class="u-text-secondary">
                Showing
                <strong>{{ $enrollments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $enrollments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $enrollments->total() }}</strong>
                enrollments
            </small>

            @if($enrollments->hasPages())
            <div>
                {{ $enrollments->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('registrar.enrollments._review-modal')


</div>
@endsection
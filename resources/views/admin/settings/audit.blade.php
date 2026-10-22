@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Audit Logs')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Audit Logs</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Audit Logs</li>
                </ol>
            </nav>
        </div>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/dtc-filters.js') }}"></script>
@endpush

@section('content')
<div class="row">
    <div class="col-12">

        {{-- Filters --}}
        @php
            $auditFilterCount = filled(request('action')) ? 1 : 0;
            $auditHasFilters  = $auditFilterCount > 0 || filled(request('search'));
        @endphp
        <form method="GET" action="{{ route('admin.settings.audit') }}" class="dtc-fl" data-dtc-filters aria-label="Filter audit logs">
            <div class="dtc-fl-toolbar">
                <div class="dtc-pay-search dtc-fl-search">
                    <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
                    <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}"
                           placeholder="Search description or action…" autocomplete="off" aria-label="Search audit logs" data-dtc-filter-search>
                </div>
                <button type="button" class="dtc-fl-toggle {{ $auditFilterCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#auditFilterPanel"
                        aria-expanded="{{ $auditFilterCount ? 'true' : 'false' }}" aria-controls="auditFilterPanel" data-dtc-filter-toggle>
                    <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
                    <span>Filters</span>
                    <span class="dtc-fl-count" data-dtc-filter-count @if(!$auditFilterCount) hidden @endif>{{ $auditFilterCount }}</span>
                </button>
            </div>

            <div class="collapse {{ $auditFilterCount ? 'show' : '' }}" id="auditFilterPanel">
                <div class="dtc-fl-panel">
                    <div class="dtc-fl-field">
                        <label for="filter-action">Action</label>
                        <select name="action" id="filter-action" class="form-control" data-dtc-filter>
                            <option value="">All actions</option>
                            @foreach ($actionTypes as $action)
                                <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                                    {{ ucwords(str_replace(['.', '_'], ' ', $action)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dtc-fl-actions">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="{{ route('admin.settings.audit') }}" class="dtc-fl-clear" data-dtc-clear @if(!$auditHasFilters) hidden @endif>Clear all</a>
                    </div>
                </div>
            </div>
        </form>

        {{-- Logs Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold u-text">Activity History</span>
                <span class="u-text-secondary-sm">{{ $logs->total() }} total</span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    @include('partials.table-rows', ['tableRecords' => $logs])
<table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr>
                                    <td style="white-space:nowrap;">
                                        <div class="u-flex-center-gap-12">
                                            <x-dtc.user-avatar :user="$log->user" :name="$log->user?->name ?? 'System'" style="width:30px; height:30px; border-radius:50%;
                                                        background:linear-gradient(135deg,var(--dtc-primary),#1a5feb);
                                                        display:flex; align-items:center; justify-content:center;
                                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;" />
                                            <span class="u-text-sm-bold-primary">
                                                {{ $log->user->name ?? 'System' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <span class="badge badge-secondary" style="font-weight:600;">
                                            {{ ucwords(str_replace(['.', '_'], ' ', $log->action)) }}
                                        </span>
                                    </td>
                                    <td style="color:var(--dtc-text-secondary);">{{ $log->description }}</td>
                                    <td style="white-space:nowrap; color:var(--dtc-text-muted);">{{ $log->ip_address ?? '—' }}</td>
                                    <td style="white-space:nowrap; color:var(--dtc-text-muted);" title="{{ $log->created_at }}">
                                        {{ $log->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4" style="color:var(--dtc-text-muted);">
                                        No activity recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($logs->hasPages())
                <div class="card-footer">
                    <div class="user-pagination">
                        {{ $logs->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection

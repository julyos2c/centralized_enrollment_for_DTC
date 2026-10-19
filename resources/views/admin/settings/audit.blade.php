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

@section('content')
<div class="row">
    <div class="col-12">

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.settings.audit') }}" class="row" style="row-gap:12px;">
                    <div class="col-12 col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="Search description or action..."
                               value="{{ request('search') }}">
                    </div>
                    <div class="col-8 col-md-4">
                        <select name="action" class="form-control">
                            <option value="">All actions</option>
                            @foreach ($actionTypes as $action)
                                <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                                    {{ ucwords(str_replace(['.', '_'], ' ', $action)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4 col-md-2 d-flex" style="gap:8px;">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i data-lucide="filter"></i>
                        </button>
                        @if (request('search') || request('action'))
                            <a href="{{ route('admin.settings.audit') }}" class="btn btn-secondary flex-fill">
                                <i data-lucide="x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Logs Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold u-text">Activity History</span>
                <span class="u-text-secondary-sm">{{ $logs->total() }} total</span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
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
                                            <div style="width:30px; height:30px; border-radius:50%;
                                                        background:linear-gradient(135deg,var(--dtc-primary),#1a5feb);
                                                        display:flex; align-items:center; justify-content:center;
                                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                                {{ $log->user ? strtoupper(substr($log->user->name, 0, 1)) : '?' }}
                                            </div>
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

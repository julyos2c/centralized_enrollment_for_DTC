@extends('adminlte::page')
@include('partials.navbar')

@section('plugins.adminUsers', true)

@section('title', 'Backup & Restore')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Backup &amp; Restore</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Backup &amp; Restore</li>
                </ol>
            </nav>
        </div>
    </div>
@endsection

@section('content')



<div class="row admin-records-page backup-page">

    {{-- ── Create Backup & Restore ───────────────────────────────────────── --}}
    <div class="col-lg-4 mb-3 order-1">

        <div class="card mb-3">
            <div class="card-body backup-action-card">
                <div class="backup-action-icon">
                    <i data-lucide="database"></i>
                </div>
                <h5 class="backup-action-title">Create Backup</h5>
                <p class="backup-action-text">
                    Download a full database copy as a <code>.sql</code> file.

                </p>
                <form id="backup-create-form" method="POST" action="{{ route('admin.settings.backup.create') }}">
                    @csrf
                    <button type="button"
                            class="btn btn-primary btn-block"
                            data-dtc-confirm
                            data-dtc-confirm-title="Create backup now?"
                            data-dtc-confirm-message="This will export a full copy of the database. Continue?"
                            data-dtc-confirm-ok="Backup Now"
                            data-dtc-confirm-tone="neutral"
                            data-dtc-confirm-form="#backup-create-form">
                        <i data-lucide="download" class="mr-2"></i> Backup Now
                    </button>
                </form>
            </div>
        </div>

        {{-- ── Restore ─── --}}
        <div class="card">
            <div class="card-header font-weight-bold u-text">
                <i data-lucide="upload" class="mr-2" style="color:var(--dtc-warning);"></i>
                Restore from Backup
            </div>
            <div class="card-body">
                <div class="backup-warning">
                    <i data-lucide="alert-triangle" class="mr-2"></i>
                    Uploaded SQL files are not restored by this application. For recovery,
                    use a reviewed backup through the restricted offline database recovery
                    process, after verifying its source and integrity.
                </div>
            </div>
        </div>

    </div>

    {{-- ── Backup History ───────────────────────────────────────── --}}
    <div class="col-lg-8 mb-3 order-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <span class="font-weight-bold u-text">
                    <i data-lucide="history" class="mr-2 u-link"></i>
                    Backup History
                </span>
                <span class="u-text-secondary-sm">
                    {{ $backups->count() }} backup{{ $backups->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body p-0">
                @forelse ($backups as $backup)
                <div class="backup-row">

                    <div class="backup-row-main">
                        {{-- Icon --}}
                        <div class="backup-row-icon dtc-icon-swatch is-{{ $backup->status === 'completed' ? 'success' : 'danger' }}" style="width:44px;height:44px;">
                            <i data-lucide="{{ $backup->status === 'completed' ? 'check-circle' : 'x-circle' }}"></i>
                        </div>

                        {{-- Info --}}
                        <div class="backup-row-info">
                            <div class="backup-row-filename">
                                {{ $backup->filename }}
                            </div>
                            <div class="backup-row-meta">
                                {{ $backup->formatted_size }} &bull;
                                {{ $backup->created_at->format('M d, Y h:i A') }} &bull;
                                by {{ $backup->creator->name ?? 'System' }}
                            </div>
                        </div>
                    </div>

                    <div class="backup-row-side">
                        {{-- Status --}}
                        <span class="backup-status-pill dtc-status-badge is-{{ $backup->status === 'completed' ? 'success' : 'danger' }}">
                            {{ ucfirst($backup->status) }}
                        </span>

                        {{-- Actions --}}
                        <div class="backup-row-actions">
                            @if($backup->status === 'completed')
                            <a href="{{ route('admin.settings.backup.download', $backup) }}"
                               class="backup-btn backup-btn-download" aria-label="Download {{ $backup->filename }}">
                                <i data-lucide="download"></i> <span>Download</span>
                            </a>
                            @endif

                            <form id="del-backup-{{ $backup->id }}"
                                  method="POST"
                                  action="{{ route('admin.settings.backup.delete', $backup) }}">
                                @csrf @method('DELETE')
                                <button type="button"
                                        class="backup-btn backup-btn-delete" aria-label="Delete {{ $backup->filename }}"
                                        data-dtc-confirm
                                        data-dtc-confirm-title="Delete backup?"
                                        data-dtc-confirm-message="Are you sure you want to permanently delete this backup file? This cannot be undone."
                                        data-dtc-confirm-ok="Delete Backup"
                                        data-dtc-confirm-tone="danger"
                                        data-dtc-confirm-form="#del-backup-{{ $backup->id }}">
                                    <i data-lucide="trash-2" aria-hidden="true"></i><span>Delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="backup-empty">
                    <i data-lucide="database" class="backup-empty-icon"></i>
                    <div class="backup-empty-title">No backups yet</div>
                    <div class="backup-empty-sub">Click "Backup Now" to create your first database backup.</div>
                </div>
                @endforelse
            </div>
        </div>

        <details class="card backup-tips">
            <summary>Backup guidance</summary>
            <ul>
                <li>Create a backup before major system changes.</li>
                <li>Keep a copy in a secure external location.</li>
                <li>Have an administrator verify backups and test recovery.</li>
            </ul>
        </details>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/admin-records.css') }}">
@endpush

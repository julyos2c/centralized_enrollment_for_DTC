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



<div class="row">

    {{-- ── Create Backup & Restore ───────────────────────────────────────── --}}
    <div class="col-lg-4 mb-3 order-2 order-lg-1">

        <div class="card mb-3">
            <div class="card-body backup-action-card">
                <div class="backup-action-icon">
                    <i data-lucide="database"></i>
                </div>
                <h5 class="backup-action-title">Create Backup</h5>
                <p class="backup-action-text">
                    Export your entire database as a <code>.sql</code> file.
                    This includes all tables, records, and data.
                </p>
                <form id="backup-create-form" method="POST" action="{{ route('admin.settings.backup.create') }}">
                    @csrf
                    <button type="button"
                            class="btn btn-primary btn-block"
                            data-dtc-confirm
                            data-dtc-confirm-title="Create Backup Now?"
                            data-dtc-confirm-message="This will export a full copy of the database. Continue?"
                            data-dtc-confirm-ok="Backup Now"
                            data-dtc-confirm-type="warning"
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
    <div class="col-lg-8 mb-3 order-1 order-lg-2">
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
                               class="backup-btn backup-btn-download">
                                <i data-lucide="download"></i> <span class="d-none d-sm-inline">Download</span>
                            </a>
                            @endif

                            <form id="del-backup-{{ $backup }}"
                                  method="POST"
                                  action="{{ route('admin.settings.backup.delete', $backup) }}">
                                @csrf @method('DELETE')
                                <button type="button"
                                        class="backup-btn backup-btn-delete"
                                        data-dtc-confirm
                                        data-dtc-confirm-title="Delete Backup?"
                                        data-dtc-confirm-message="Are you sure you want to permanently delete this backup file? This cannot be undone."
                                        data-dtc-confirm-ok="Delete Backup"
                                        data-dtc-confirm-form="#del-backup-{{ $backup }}">
                                    <i data-lucide="trash-2"></i>
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

        {{-- Info card --}}
        <div class="card card-gradient mt-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <h6 style="font-weight:700; margin-bottom:12px;">
                    <i data-lucide="info" class="mr-2 u-accent"></i>
                    Backup Tips
                </h6>
                <div style="font-size:13px; line-height:1.8; opacity:0.9;">
                    @foreach([
                        'Create backups before making major system changes.',
                        'Download and store backups in a secure external location.',
                        'Test restores periodically to make sure backups are valid.',
                        'Backups are stored in <code style="background:rgba(255,255,255,0.15); padding:1px 6px; border-radius:4px;">storage/app/backups/</code>',
                    ] as $tip)
                    <div style="display:flex; gap:10px; margin-bottom:8px;">
                        <i data-lucide="check-circle" style="color:#FFC72C; margin-top:3px; flex-shrink:0;"></i>
                        <span>{!! $tip !!}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('css')
<style>
/* -- Backup & Restore ------------------------------------------- */
.backup-action-card {
    text-align: center;
    padding: 32px 24px;
}
.backup-action-icon {
    width: 72px; height: 72px; border-radius: 20px;
    background: linear-gradient(135deg,#0F4CDB,#1a5feb);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 28px; color: #FFC72C;
}
.backup-action-title { font-weight: 700; color: var(--dtc-text); margin-bottom: 8px; }
.backup-action-text  { font-size: 13px; color: var(--dtc-text-secondary); line-height: 1.6; margin-bottom: 20px; }

.backup-warning {
    background: var(--_bw-bg, #FEF9C3);
    border: 1.5px solid var(--_bw-border, #FDE68A);
    border-radius: 12px;
    padding: 14px 16px; margin-bottom: 16px; font-size: 12px;
    color: var(--_bw-text, #92400E);
    display: flex; align-items: flex-start; gap: 8px;
}
body.dtc-dark .backup-warning {
    --_bw-bg:     rgba(253, 230, 138, 0.10);
    --_bw-border: rgba(253, 230, 138, 0.25);
    --_bw-text:   #FDE68A;
}

.backup-drop-zone {
    border: 2px dashed var(--dtc-border); border-radius: 12px;
    padding: 24px; text-align: center; cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    background: var(--dtc-surface-soft);
}
.backup-drop-icon  { font-size: 28px; color: var(--dtc-text-muted); display: block; margin-bottom: 8px; }
.backup-drop-title { font-size: 13px; font-weight: 600; color: var(--dtc-text); margin-bottom: 4px; }
.backup-drop-sub   { font-size: 12px; color: var(--dtc-text-muted); word-break: break-word; }

.backup-empty { padding: 48px 20px; text-align: center; }
.backup-empty-icon  { font-size: 40px; color: var(--dtc-border); display: block; margin-bottom: 14px; }
.backup-empty-title { font-size: 14px; font-weight: 600; color: var(--dtc-text); margin-bottom: 6px; }
.backup-empty-sub   { font-size: 13px; color: var(--dtc-text-muted); }

/* Backup history row */
.backup-row {
    padding: 16px 20px;
    border-bottom: 1px solid var(--dtc-border-soft);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.backup-row:last-child { border-bottom: none; }

.backup-row-main {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 1 1 240px;
}
.backup-row-icon {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
.backup-row-info { flex: 1; min-width: 0; }
.backup-row-filename {
    font-size: 13px; font-weight: 600; color: var(--dtc-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.backup-row-meta {
    font-size: 12px; color: var(--dtc-text-secondary); margin-top: 3px;
    white-space: normal;
}

.backup-row-side {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
}

.backup-status-pill {
    font-size: 11px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px; flex-shrink: 0; white-space: nowrap;
}

.backup-row-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.backup-btn {
    border: none; padding: 6px 12px; border-radius: 8px;
    font-size: 11px; font-weight: 600; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px; cursor: pointer;
}
.backup-btn-download { background: var(--dtc-primary-soft); color: var(--dtc-primary); }
.backup-btn-download:hover { background: var(--dtc-primary-soft); color: var(--dtc-primary); text-decoration: none; filter: brightness(0.96); }
.backup-btn-delete        { background: rgba(220,38,38,0.10); color: var(--dtc-danger); }
.backup-btn-delete:hover  { background: rgba(220,38,38,0.18); }
body.dtc-dark .backup-btn-download       { background: rgba(99,130,245,0.25); color: #93C5FD; border: 1px solid rgba(99,130,245,0.35); }
body.dtc-dark .backup-btn-download:hover { background: rgba(99,130,245,0.38); color: #BFDBFE; filter: none; }
body.dtc-dark .backup-btn-delete       { background: rgba(220,38,38,0.18); color: #FCA5A5; }
body.dtc-dark .backup-btn-delete:hover { background: rgba(220,38,38,0.28); }

/* -- Mobile ----------------------------------------------------- */
@media (max-width: 575.98px) {
    .backup-row {
        flex-direction: column;
        align-items: stretch;
    }
    .backup-row-side {
        justify-content: space-between;
        margin-left: 0;
        width: 100%;
    }
}
</style>
@endpush

@section('js')
<script>
// File drop zone
function updateFileName(input) {
    const label = document.getElementById('file-name');
    const zone  = document.getElementById('drop-zone');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const size = (file.size / 1024).toFixed(1) + ' KB';
        label.textContent = file.name + ' (' + size + ')';
        label.style.color = 'var(--dtc-success)';
        zone.style.borderColor = 'var(--dtc-primary)';
        zone.style.background  = 'var(--dtc-primary-soft)';
    }
}

// Drag and drop
const zone = document.getElementById('drop-zone');
if (zone) {
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--dtc-primary)';
        this.style.background  = 'var(--dtc-primary-soft)';
    });
    zone.addEventListener('dragleave', function() {
        this.style.borderColor = 'var(--dtc-border)';
        this.style.background  = 'var(--dtc-surface-soft)';
    });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        const input = document.getElementById('sql-file');
        input.files = e.dataTransfer.files;
        updateFileName(input);
    });
}
</script>
@endsection

@extends('adminlte::page')
@include('partials.navbar')

@section('plugins.adminUsers', true)

@section('title', 'Manage Users')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Manage Users</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Create and manage system user accounts
            </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm add-user-btn" data-toggle="modal" data-target="#createUserModal">
            <i data-lucide="user-plus" class="mr-1"></i> 
            <span class="add-user-text">Add New User</span>
        </button>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/dtc-filters.js') }}"></script>
@endpush

@section('content')


@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

{{-- Stats Row --}}
<div class="row mb-2">
    @php
        $roleStatConfig = [
            'admin'         => ['icon' => 'user-check',    'color' => 'primary'],
            'registrar'     => ['icon' => 'id-card',        'color' => 'success'],
            'cashier'       => ['icon' => 'receipt',  'color' => 'warning'],
            'student'       => ['icon' => 'graduation-cap',  'color' => 'info'],
            'alumni'        => ['icon' => 'award',          'color' => 'danger'],
            'new_applicant' => ['icon' => 'user-plus',      'color' => 'neutral'],
        ];
    @endphp
    @foreach($roleStatConfig as $role => $cfg)
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <x-dtc.stat-card
            icon="{{ $cfg['icon'] }}" color="{{ $cfg['color'] }}"
            label="{{ ucwords(str_replace('_',' ', $role)) }}"
            value="{{ $roleCounts[$role] ?? 0 }}" />
    </div>
    @endforeach
</div>

{{-- Search & Filter --}}
@php
    $userFilterCount = collect(['role', 'status'])->filter(fn ($k) => filled(request($k)))->count();
    $userHasFilters  = $userFilterCount > 0 || filled(request('search'));
@endphp
<form method="GET" action="{{ route('admin.users.index') }}" id="users-filter-form" class="dtc-fl" data-dtc-filters aria-label="Filter users">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}"
                   placeholder="Search name or email…" autocomplete="off" aria-label="Search users" data-dtc-filter-search>
        </div>
        <button type="button" class="dtc-fl-toggle {{ $userFilterCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#usersFilterPanel"
                aria-expanded="{{ $userFilterCount ? 'true' : 'false' }}" aria-controls="usersFilterPanel" data-dtc-filter-toggle>
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filters</span>
            <span class="dtc-fl-count" data-dtc-filter-count @if(!$userFilterCount) hidden @endif>{{ $userFilterCount }}</span>
        </button>
    </div>

    <div class="collapse {{ $userFilterCount ? 'show' : '' }}" id="usersFilterPanel">
        <div class="dtc-fl-panel">
            <div class="dtc-fl-field">
                <label for="filter-role">Role</label>
                <select name="role" id="filter-role" class="form-control" data-dtc-filter>
                    <option value="">All roles</option>
                    @foreach(['admin','registrar','cashier','student','alumni','new_applicant'] as $r)
                        <option value="{{ $r }}" {{ request('role') === $r ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_',' ', $r)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="dtc-fl-field">
                <label for="filter-status">Status</label>
                <select name="status" id="filter-status" class="form-control" data-dtc-filter>
                    <option value="">All statuses</option>
                    @foreach(['active','pending','locked'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                            {{ ucfirst($s) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('admin.users.index') }}" class="dtc-fl-clear" data-dtc-clear @if(!$userHasFilters) hidden @endif>Clear all</a>
            </div>
        </div>
    </div>
</form>

{{-- Users Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text">All Users</span>
        <span class="u-text-secondary-sm" id="users-count">{{ $users->total() }} total</span>
    </div>

    <div id="users-table-wrapper">
    <div class="card-body p-0">
        <div class="table-responsive">
        @include('partials.table-rows', ['tableRecords' => $users])
<table class="table table-bordered mb-0 users-directory" aria-label="User accounts">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($users as $user)
                <tr data-name="{{ strtolower($user->name) }}"
                    data-email="{{ strtolower($user->email) }}"
                    data-role="{{ strtolower($user->roles->first()?->name ?? '') }}"
                    data-status="{{ strtolower($user->status) }}">
                    <td>
                        <div  class="u-flex-center-gap-12">
                            <x-dtc.user-avatar :user="$user" style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--dtc-primary);color:#fff;flex-shrink:0;font-weight:700;" />

                            <div  class="u-text-sm-bold-primary">
                                {{ $user->name }}
                            </div>
                        </div>
                    </td>

                    <td class="u-text-secondary-sm" data-label="Email">
                        {{ $user->email }}
                    </td>

                    <td data-label="Role">
                        @foreach($user->roles as $role)
                            @php
                                $rc = [
                                    'admin'         => ['tone'=>'primary'],
                                    'registrar'     => ['tone'=>'success'],
                                    'cashier'       => ['tone'=>'warning'],
                                    'student'       => ['tone'=>'purple'],
                                    'alumni'        => ['tone'=>'danger'],
                                    'new_applicant' => ['tone'=>'info'],
                                ][$role->name] ?? ['tone'=>'neutral'];
                            @endphp

                            <span class="dtc-status-badge is-{{ $rc['tone'] ?? 'neutral' }}"
                                  style="font-weight:700;">
                                {{ ucwords(str_replace('_',' ', $role->name)) }}
                            </span>
                        @endforeach
                    </td>

                    <td data-label="Status">
                        @php
                            $statusVariant = ['active' => 'success', 'locked' => 'danger', 'pending' => 'warning'][$user->status] ?? 'neutral';
                        @endphp
                        <x-dtc.status-badge :status="$user->status" :variant="$statusVariant" />
                    </td>

                    <td data-label="Joined" style="font-size:12px; color:var(--dtc-text-muted);">
                        {{ $user->created_at->format('M d, Y') }}
                    </td>

                    <td data-label="Actions">
                        <div class="u-actions-gap">
                            <button type="button"
                                    class="btn-edit-user"
                                    aria-label="Edit {{ $user->name }}"
                                    data-id="{{ $user->id }}"
                                    data-url="{{ route('admin.users.edit', $user) }}">
                                <i data-lucide="pencil"></i>
                            </button>

                            @if($user->id !== auth()->id())
                            <form id="del-user-{{ $user->id }}"
                                  method="POST"
                                  action="{{ route('admin.users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                        class="dtc-icon-btn danger"
                                        title="Delete user"
                                        aria-label="Delete {{ $user->name }}"
                                        data-dtc-confirm
                                        data-dtc-confirm-title="Delete user?"
                                        data-dtc-confirm-message="The account for {subject} will be permanently deleted."
                                        data-dtc-confirm-subject="{{ $user->name }}"
                                        data-dtc-confirm-ok="Delete"
                                        data-dtc-confirm-tone="danger"
                                        data-dtc-confirm-form="#del-user-{{ $user->id }}">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="6"
                        class="text-center py-5 u-text-muted"
                        >
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

    {{-- Pagination --}}
        <div class="card-footer u-panel-footer"
            >

            <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15"
                >

                <small  class="u-text-secondary">
                    Showing
                    <strong>{{ $users->firstItem() ?? 0 }}</strong>
                    to
                    <strong>{{ $users->lastItem() ?? 0 }}</strong>
                    of
                    <strong>{{ $users->total() }}</strong>
                    users
                </small>

                <div class="user-pagination">
                    {{ $users->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>

            </div>
        </div>
    </div>{{-- /#users-table-wrapper --}}
</div>

<!-- CREATE USER MODAL -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content dtc-modal-content">

            {{-- Header --}}
            <div class="dtc-modal-header">
                <div class="dtc-modal-header-icon">
                    <i data-lucide="user-plus"></i>
                </div>
                <div class="dtc-modal-header-text">
                    <h5>Add New User</h5>
                    <p>Create an account and assign a role</p>
                </div>
                <button type="button" class="dtc-modal-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.users.store') }}" data-password-admin>
                @csrf
                <div class="modal-body dtc-modal-body">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Full Name</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="user" class="dtc-input-icon"></i>
                                    <input type="text" name="name" class="form-control dtc-input-with-icon"
                                           value="{{ old('name') }}"
                                           placeholder="Enter full name" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="dtc-form-label">Email Address</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="mail" class="dtc-input-icon"></i>
                                    <input type="email" name="email" class="form-control dtc-input-with-icon"
                                           value="{{ old('email') }}"
                                           placeholder="Enter email address" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="dtc-form-label">Temporary Password</label>
                        <div class="dtc-input-icon-group">
                            <i data-lucide="lock" class="dtc-input-icon"></i>
                            <input type="password" name="password" class="form-control dtc-input-with-icon"
                                   placeholder="Min. 6 characters" autocomplete="new-password" minlength="6" required>
                        </div>
                        <small class="dtc-form-hint">
                            The user should change this after first login.
                        </small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="dtc-form-label dtc-form-label-block">Assign Role</label>
                        <div class="row">
                            @php
                                // Only staff roles may be manually created.
                                // Students enter via Pre-Enrollment or Import.
                                // Alumni are transitioned from existing student accounts.
                                $staffRoles = ['admin','registrar','cashier'];
                                $staffRoleConfig = [
                                    'admin'     => ['tone'=>'primary', 'icon'=>'user-check'],
                                    'registrar' => ['tone'=>'success', 'icon'=>'id-card'],
                                    'cashier'   => ['tone'=>'warning', 'icon'=>'receipt'],
                                ];
                            @endphp
                            @foreach($roles->whereIn('name', $staffRoles) as $role)
                            @php $rc = $staffRoleConfig[$role->name] ?? ['tone'=>'neutral','icon'=>'user']; @endphp
                            <div class="col-6 col-md-4 mb-2">
                                <label class="dtc-role-option-label">
                                    <input type="radio" name="role" value="{{ $role->name }}"
                                           class="create-role-radio dtc-sr-only">
                                    <div class="create-role-option">
                                        <div class="dtc-icon-swatch is-{{ $rc['tone'] }} dtc-role-icon" data-role="{{ $role->name }}">
                                            <i data-lucide="{{ $rc['icon'] }}"></i>
                                        </div>
                                        <span class="dtc-role-name">
                                            {{ ucfirst($role->name) }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <small class="dtc-form-hint mt-2 d-block">
                            <i data-lucide="info" style="width:12px;height:12px;display:inline;"></i>
                            Students enter through Pre-Enrollment or Bulk Import. Alumni are transitioned from student accounts. Only staff accounts are manually created here.
                        </small>
                    </div>

                </div>

                <div class="modal-footer dtc-modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="user-plus" class="mr-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="edit-modal-name" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content dtc-modal-content">

            {{-- Header --}}
            <div class="dtc-modal-header">
                <div class="dtc-modal-header-avatar" id="edit-avatar">U</div>
                <div class="dtc-modal-header-text">
                    <h5 id="edit-modal-name">Edit User</h5>
                    <p id="edit-modal-email">user@dtc.edu.ph</p>
                </div>
                <button type="button" class="dtc-modal-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="dtc-edit-tabs-wrap">
                <ul class="nav nav-tabs border-0" id="editModalTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active dtc-edit-tab" id="modal-tab-account"
                           data-toggle="tab" href="#modal-pane-account" role="tab">
                            <i data-lucide="pencil" style="width:13px;height:13px;margin-right:5px;vertical-align:-1px;"></i>
                            Account
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link dtc-edit-tab" id="modal-tab-security"
                           data-toggle="tab" href="#modal-pane-security" role="tab">
                            <i data-lucide="lock" style="width:13px;height:13px;margin-right:5px;vertical-align:-1px;"></i>
                            Security
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">

                {{-- Tab 1: Edit Account --}}
                <div class="tab-pane fade show active" id="modal-pane-account" role="tabpanel">
                    <form method="POST" id="edit-user-form" action="">
                        @csrf @method('PUT')
                        <div class="modal-body dtc-modal-body">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="dtc-form-label">Full Name</label>
                                        <div class="dtc-input-icon-group">
                                            <i data-lucide="user" class="dtc-input-icon"></i>
                                            <input type="text" name="name" id="edit-name"
                                                   class="form-control dtc-input-with-icon" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="dtc-form-label">Email Address</label>
                                        <div class="dtc-input-icon-group">
                                            <i data-lucide="mail" class="dtc-input-icon"></i>
                                            <input type="email" name="email" id="edit-email"
                                                   class="form-control dtc-input-with-icon" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="dtc-form-label">Role</label>
                                        <div class="dtc-input-icon-group" id="edit-role-select-wrap">
                                            <i data-lucide="user-check" class="dtc-input-icon"></i>
                                            <select name="role" id="edit-role"
                                                    class="form-control dtc-input-with-icon">
                                                @foreach($roles->whereIn('name', ['admin','registrar','cashier']) as $role)
                                                    <option value="{{ $role->name }}">
                                                        {{ ucfirst($role->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div id="edit-role-readonly-wrap" style="display:none;">
                                            <input type="hidden" name="role" id="edit-role-hidden">
                                            <div style="display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;background:var(--dtc-surface-soft);border:1px solid var(--dtc-border);font-size:13px;color:var(--dtc-text-muted);">
                                                <i data-lucide="lock" style="width:14px;height:14px;flex-shrink:0;"></i>
                                                <span id="edit-role-readonly-label">---</span>
                                                <span style="margin-left:auto;font-size:11px;">Managed by system</span>
                                            </div>
                                            <small class="dtc-form-hint">Role changes are managed by the Registrar.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label class="dtc-form-label dtc-form-label-block">Account Status</label>
                                        <div style="display:flex;gap:8px;">
                                            @foreach(['active','pending','locked'] as $status)
                                            <label class="dtc-status-option-label">
                                                <input type="radio" name="status" value="{{ $status }}" class="edit-status-radio dtc-sr-only">
                                                <div class="edit-status-option">
                                                    <i data-lucide="{{ $status === 'active' ? 'check-circle' : ($status === 'locked' ? 'lock' : 'clock') }}" style="color:{{ $status === 'active' ? 'var(--dtc-success)' : ($status === 'locked' ? 'var(--dtc-danger)' : 'var(--dtc-warning)') }};"></i>
                                                    <div class="dtc-status-label">{{ ucfirst($status) }}</div>
                                                </div>
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer dtc-modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="save" class="mr-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Tab 2: Security --}}
                <div class="tab-pane fade" id="modal-pane-security" role="tabpanel">
                    <form method="POST" id="reset-password-form" action="" data-password-admin>
                        @csrf @method('PUT')
                        <div class="modal-body dtc-modal-body">

                            <div class="dtc-security-info mb-3">
                                <i data-lucide="info" class="dtc-security-info-icon"></i>
                                <span>Set a temporary password. The user must change it at their next login.</span>
                            </div>

                            <div class="form-group">
                                <label class="dtc-form-label" for="reset-new-password">New Password</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="lock" class="dtc-input-icon"></i>
                                    <input type="password" name="password" id="reset-new-password"
                                           class="form-control dtc-input-with-icon"
                                           placeholder="Min. 6 characters"
                                           autocomplete="new-password" minlength="6">
                                    <button type="button" class="dtc-password-toggle" data-password-toggle="reset-new-password" aria-controls="reset-new-password" aria-label="Show new password" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label class="dtc-form-label" for="reset-confirm-password">Confirm Password</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="lock" class="dtc-input-icon"></i>
                                    <input type="password" name="password_confirmation" id="reset-confirm-password"
                                           class="form-control dtc-input-with-icon"
                                           placeholder="Re-enter password"
                                           autocomplete="new-password" minlength="6">
                                    <button type="button" class="dtc-password-toggle" data-password-toggle="reset-confirm-password" aria-controls="reset-confirm-password" aria-label="Show confirm password" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                                </div>
                                <small class="dtc-pw-match-hint" id="modal-pw-match-hint"></small>
                            </div>

                        </div>
                        <div class="modal-footer dtc-modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn dtc-btn-reset" id="modal-pw-submit">
                                <i data-lucide="key" class="mr-1"></i> Reset user password
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
{{-- Styles live in public/css/admin-users.css (loaded via plugins.adminUsers) --}}

@section('js')
<script src="{{ asset('js/admin-user-modal.js') }}" defer></script>
<script>
// =============================================================================
// USERS INDEX - JavaScript
// Single source of truth. Event delegation used throughout so no re-binding
// is ever needed after AJAX re-renders the table.
// =============================================================================

// -- Shared: open edit modal with user data ------------------------------------
function openEditModal(btn) {
    const url     = btn.dataset.url;
    const editUrl = url.replace('/edit', '');

    document.getElementById('edit-user-form').action      = editUrl;
    document.getElementById('reset-password-form').action = editUrl + '/reset-password';

    document.getElementById('reset-new-password').value       = '';
    document.getElementById('reset-confirm-password').value   = '';
    document.getElementById('reset-new-password').dispatchEvent(new Event('input', { bubbles: true }));
    document.getElementById('modal-pw-match-hint').textContent = '';

    document.getElementById('modal-tab-account').click();
    document.getElementById('edit-avatar').textContent = '…';
    document.getElementById('edit-modal-name').textContent  = 'Loading...';
    document.getElementById('edit-modal-email').textContent = '';

    $('#editUserModal').modal('show');

    window.dtcFetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(function (user) {
        const avatar = document.getElementById('edit-avatar');
        const initial = user.name.charAt(0).toUpperCase();
        avatar.textContent = initial;
        if (user.avatar_url) {
            const image = document.createElement('img');
            image.alt = user.name + ' profile photo';
            image.addEventListener('error', function () {
                if (image.parentNode === avatar) avatar.textContent = initial;
            }, { once: true });
            image.src = user.avatar_url;
            avatar.replaceChildren(image);
        }
        document.getElementById('edit-modal-name').textContent  = user.name;
        document.getElementById('edit-modal-email').textContent = user.email;
        document.getElementById('edit-name').value  = user.name;
        document.getElementById('edit-email').value = user.email;

        var staffRoles      = ['admin', 'registrar', 'cashier'];
        var isStaff         = staffRoles.indexOf(user.role) !== -1;
        var selectWrap      = document.getElementById('edit-role-select-wrap');
        var readonlyWrap    = document.getElementById('edit-role-readonly-wrap');
        var roleSelectEl    = document.getElementById('edit-role');
        var roleHidden      = document.getElementById('edit-role-hidden');
        var roleReadonlyLbl = document.getElementById('edit-role-readonly-label');

        if (isStaff) {
            selectWrap.style.display   = '';
            readonlyWrap.style.display = 'none';
            roleSelectEl.required      = true;
            roleSelectEl.value         = user.role;
        } else {
            selectWrap.style.display   = 'none';
            readonlyWrap.style.display = '';
            roleSelectEl.required      = false;
            roleHidden.value           = user.role;
            roleReadonlyLbl.textContent = user.role.replace(/_/g, ' ')
                .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        }

        document.querySelectorAll('.edit-status-radio').forEach(function (r) {
            r.checked = (r.value === user.status);
            var opt = r.nextElementSibling;
            opt.style.borderColor = r.checked ? 'var(--dtc-primary)' : 'var(--dtc-border)';
            opt.style.background  = r.checked ? 'var(--dtc-primary-soft)' : 'var(--dtc-surface-soft)';
        });
    })
    .catch(function (error) {
        window.dtcRequestFailure(error);
        document.getElementById('edit-modal-name').textContent = 'Error loading user';
    });
}

// -- Delegated click - edit buttons (works after every AJAX re-render) ---------
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-edit-user');
    if (btn) openEditModal(btn);
});

// -- AJAX live search + filter -------------------------------------------------
(function () {
    var searchInput  = document.querySelector('input[name="search"]');
    var roleSelect   = document.querySelector('select[name="role"]');
    var statusSelect = document.querySelector('select[name="status"]');
    var wrapper      = document.getElementById('users-table-wrapper');
    var countEl      = document.getElementById('users-count');
    var baseUrl      = '{{ route("admin.users.index") }}';
    var debounceTimer  = null;
    var currentRequest = null;

    function setLoading(on) {
        wrapper.style.opacity      = on ? '0.45' : '1';
        wrapper.style.pointerEvents = on ? 'none' : '';
    }

    function swapWrapper(html) {
        var doc      = new DOMParser().parseFromString(html, 'text/html');
        var newWrap  = doc.getElementById('users-table-wrapper');
        var newCount = doc.getElementById('users-count');
        if (newWrap)             wrapper.innerHTML     = newWrap.innerHTML;
        if (newCount && countEl) countEl.textContent   = newCount.textContent;
        if (window.lucide)       lucide.createIcons();
    }

    function doRequest(url) {
        if (currentRequest) { currentRequest.abort(); currentRequest = null; }
        setLoading(true);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status === 200) swapWrapper(xhr.responseText);
            else window.dtcHttpFailure(xhr.status);
            setLoading(false);
        };
        xhr.onerror = function () { setLoading(false); window.dtcHttpFailure(0); };
        xhr.send();
        currentRequest = xhr;
    }

    function buildUrl(extra) {
        var p = new URLSearchParams(extra || {});
        p.set('search', searchInput.value.trim());
        p.set('role',   roleSelect.value);
        p.set('status', statusSelect.value);
        p.set('per_page', document.querySelector('[data-table-rows-select]')?.value || new URLSearchParams(location.search).get('per_page') || '15');
        return baseUrl + '?' + p.toString();
    }

    function fetchResults() { doRequest(buildUrl()); }

    // Typing - debounced 300 ms
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchResults, 300);
    });

    // Dropdowns - instant
    roleSelect.addEventListener('change',   fetchResults);
    statusSelect.addEventListener('change', fetchResults);

    // Filter form submit (Enter key or Filter button)
    searchInput.closest('form').addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        fetchResults();
    });

    // Delegated pagination - catches links even after AJAX re-renders
    wrapper.addEventListener('click', function (e) {
        var link = e.target.closest('.user-pagination a');
        if (!link) return;
        e.preventDefault();
        var pageParams = new URL(link.href).searchParams;
        doRequest(buildUrl({ page: pageParams.get('page') }));
    });
})();

// -- Create User - role radio styling -----------------------------------------
document.querySelectorAll('.create-role-radio').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.create-role-option').forEach(function (opt) {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background  = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = 'var(--dtc-primary)';
        this.nextElementSibling.style.background  = 'var(--dtc-primary-soft)';
    });
});

// -- Edit modal - status radio styling ----------------------------------------
document.addEventListener('change', function (e) {
    if (!e.target.matches('.edit-status-radio')) return;
    document.querySelectorAll('.edit-status-option').forEach(function (opt) {
        opt.style.borderColor = 'var(--dtc-border)';
        opt.style.background  = 'var(--dtc-surface-soft)';
    });
    e.target.nextElementSibling.style.borderColor = 'var(--dtc-primary)';
    e.target.nextElementSibling.style.background  = 'var(--dtc-primary-soft)';
});

// -- Accessibility: blur focused element before Bootstrap sets aria-hidden -----
['#editUserModal', '#createUserModal'].forEach(function (id) {
    $(id).on('hide.bs.modal', function () {
        if (document.activeElement && this.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });
});

// -- Auto-open create modal on validation errors -------------------------------
@if($errors->any() && old('name'))
    $(document).ready(function () { $('#createUserModal').modal('show'); });
@endif
</script>
@endsection

@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Edit User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Edit User</h4>
            <p class="mb-0 u-text-secondary-sm">Update account details and role</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to Users
        </a>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        {{-- User Profile Card --}}
        <div class="card mb-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:56px; height:56px; border-radius:50%;
                                background:rgba(255,255,255,0.2); display:flex;
                                align-items:center; justify-content:center;
                                color:#fff; font-weight:800; font-size:22px; flex-shrink:0;
                                border:2px solid rgba(255,255,255,0.3);">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#fff;">
                            {{ $user->name }}
                        </div>
                        <div style="font-size:13px; color:rgba(255,255,255,0.75);">
                            {{ $user->email }}
                        </div>
                        <div style="font-size:12px; color:#FFC72C; margin-top:4px; font-weight:600;">
                            Joined {{ $user->created_at->format('M d, Y') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Card --}}
        <div class="card">

            {{-- Tabs --}}
            <div class="card-header p-0" style="border-bottom:1px solid var(--dtc-border);">
                <ul class="nav nav-tabs border-0 px-3 pt-2" id="editUserTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active dtc-edit-tab" id="tab-account" data-toggle="tab"
                           href="#pane-account" role="tab">
                            <i data-lucide="pencil" style="width:13px;height:13px;margin-right:5px;vertical-align:-1px;"></i>
                            Edit Account
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link dtc-edit-tab" id="tab-security" data-toggle="tab"
                           href="#pane-security" role="tab">
                            <i data-lucide="lock" style="width:13px;height:13px;margin-right:5px;vertical-align:-1px;"></i>
                            Security
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">

                {{-- ── Tab 1: Edit Account ── --}}
                <div class="tab-pane fade show active" id="pane-account" role="tabpanel">
                    <div class="card-body">

                        @if ($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif

                        <form method="POST" action="{{ route('admin.users.update', $user) }}">
                            @csrf @method('PUT')

                            <div class="form-group">
                                <label class="dtc-form-label">Full Name</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="user" class="dtc-input-icon"></i>
                                    <input type="text" name="name"
                                           class="form-control dtc-input-with-icon"
                                           value="{{ old('name', $user->name) }}" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="dtc-form-label">Email Address</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="mail" class="dtc-input-icon"></i>
                                    <input type="email" name="email"
                                           class="form-control dtc-input-with-icon"
                                           value="{{ old('email', $user->email) }}" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="dtc-form-label">Role</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="shield" class="dtc-input-icon"></i>
                                    <input type="text" class="form-control dtc-input-with-icon"
                                           value="{{ ucfirst(str_replace('_',' ', $user->roles->first()?->name ?? '—')) }}"
                                           readonly disabled
                                           style="background:var(--dtc-surface-soft); color:var(--dtc-text-muted); cursor:not-allowed;">
                                </div>
                                <small class="dtc-form-hint">
                                    Role changes for students, alumni, and applicants go through the Registrar flow.
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="dtc-form-label">Account Status</label>
                                <div class="d-flex" style="gap:10px;">
                                    @foreach(['active','pending','locked'] as $status)
                                    <label class="dtc-status-option-label">
                                        <input type="radio" name="status" value="{{ $status }}"
                                               class="edit-status-radio dtc-sr-only"
                                               {{ old('status', $user->status) === $status ? 'checked' : '' }}>
                                        <div class="edit-status-option">
                                            <i data-lucide="{{ $status === 'active' ? 'check-circle' : ($status === 'locked' ? 'lock' : 'clock') }}"
                                               style="color:{{ $status === 'active' ? 'var(--dtc-success)' : ($status === 'locked' ? 'var(--dtc-danger)' : 'var(--dtc-warning)') }};"></i>
                                            <div class="dtc-status-label">{{ ucfirst($status) }}</div>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="d-flex u-gap-10">
                                <a href="{{ route('admin.users.index') }}"
                                   class="btn btn-secondary flex-fill">Cancel</a>
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i data-lucide="save" class="mr-1"></i> Save Changes
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

                {{-- ── Tab 2: Security / Reset Password ── --}}
                <div class="tab-pane fade" id="pane-security" role="tabpanel">
                    <div class="card-body">

                        @if(session('password_reset_success'))
                            <div class="alert alert-success">
                                <i data-lucide="check-circle" class="mr-1"></i>
                                Password reset successfully. The user will be prompted to change it on next login.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.users.resetPassword', $user) }}">
                            @csrf @method('PUT')

                            {{-- Info banner --}}
                            <div class="dtc-security-info mb-3">
                                <i data-lucide="info" class="dtc-security-info-icon"></i>
                                <span>Set a temporary password for this user. They'll be required to change it after their next login. Passwords are always stored hashed.</span>
                            </div>

                            <div class="form-group">
                                <label class="dtc-form-label">New Password</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="lock" class="dtc-input-icon"></i>
                                    <input type="password" name="password" id="pw-new"
                                           class="form-control dtc-input-with-icon"
                                           placeholder="Min. 6 characters" required minlength="6"
                                           autocomplete="new-password">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="dtc-form-label">Confirm Password</label>
                                <div class="dtc-input-icon-group">
                                    <i data-lucide="lock" class="dtc-input-icon"></i>
                                    <input type="password" name="password_confirmation" id="pw-confirm"
                                           class="form-control dtc-input-with-icon"
                                           placeholder="Re-enter password" required minlength="6"
                                           autocomplete="new-password">
                                </div>
                                <small class="dtc-form-hint dtc-pw-match-hint" id="pw-match-hint"></small>
                            </div>

                            <div class="d-flex u-gap-10">
                                <a href="{{ route('admin.users.index') }}"
                                   class="btn btn-secondary flex-fill">Cancel</a>
                                <button type="submit" class="btn dtc-btn-reset flex-fill" id="pw-submit-btn">
                                    <i data-lucide="key" class="mr-1"></i> Reset Password
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>{{-- /.tab-content --}}
        </div>

    </div>
</div>
@endsection

@section('js')
<script>
/* -- Status radio highlight -- */
document.querySelectorAll('.edit-status-radio').forEach(function(radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.edit-status-option').forEach(function(opt) {
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background  = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = 'var(--dtc-primary)';
        this.nextElementSibling.style.background  = 'var(--dtc-primary-soft)';
    });
    if (radio.checked) radio.dispatchEvent(new Event('change'));
});

/* -- Password match feedback -- */
var pwNew     = document.getElementById('pw-new');
var pwConfirm = document.getElementById('pw-confirm');
var pwHint    = document.getElementById('pw-match-hint');
var pwSubmit  = document.getElementById('pw-submit-btn');

function checkPwMatch() {
    if (!pwConfirm.value) { pwHint.textContent = ''; return; }
    if (pwNew.value === pwConfirm.value) {
        pwHint.textContent = '(ok) Passwords match';
        pwHint.style.color = 'var(--dtc-success)';
    } else {
        pwHint.textContent = 'Passwords do not match';
        pwHint.style.color = 'var(--dtc-danger)';
    }
}

pwNew.addEventListener('input', checkPwMatch);
pwConfirm.addEventListener('input', checkPwMatch);

/* -- Re-open Security tab if there were password errors -- */
@if($errors->has('password') || $errors->has('password_confirmation') || session('active_tab') === 'security')
    document.addEventListener('DOMContentLoaded', function () {
        var secTab = document.getElementById('tab-security');
        if (secTab) secTab.click();
    });
@endif
</script>
@endsection

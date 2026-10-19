@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Edit User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Edit User</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Update account details and role
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Users
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
                        <div style="font-size:12px; color:#FFC72C; margin-top:4px;
                                    font-weight:600;">
                            Joined {{ $user->created_at->format('M d, Y') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-edit mr-2" style="color:#0F4CDB;"></i>
                Edit Account
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf @method('PUT')

                    <div class="form-group">
                        <label>Full Name</label>
                        <div style="position:relative;">
                            <i class="fas fa-user" style="position:absolute; left:14px;
                               top:50%; transform:translateY(-50%); color:#94A3B8; font-size:13px;"></i>
                            <input type="text" name="name" class="form-control"
                                   style="padding-left:38px;"
                                   value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div style="position:relative;">
                            <i class="fas fa-envelope" style="position:absolute; left:14px;
                               top:50%; transform:translateY(-50%); color:#94A3B8; font-size:13px;"></i>
                            <input type="email" name="email" class="form-control"
                                   style="padding-left:38px;"
                                   value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}"
                                    {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_',' ', $role->name)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Account Status</label>
                        <div class="row">
                            @foreach(['active','pending','locked'] as $status)
                            <div class="col-4">
                                <label style="cursor:pointer; width:100%;">
                                    <input type="radio" name="status" value="{{ $status }}"
                                           style="display:none;" class="status-radio"
                                           {{ old('status', $user->status) === $status ? 'checked' : '' }}>
                                    <div class="status-option"
                                         style="border:2px solid #E2E8F0; border-radius:12px;
                                                padding:12px; text-align:center; transition:all 0.2s;
                                                background:#F8FAFC;">
                                        <i class="fas {{ $status === 'active' ? 'fa-check-circle' : ($status === 'locked' ? 'fa-lock' : 'fa-clock') }}"
                                           style="font-size:18px; display:block; margin-bottom:6px;
                                                  color:{{ $status === 'active' ? '#22C55E' : ($status === 'locked' ? '#EF4444' : '#F59E0B') }};"></i>
                                        <div style="font-size:12px; font-weight:600; color:#1E293B;">
                                            {{ ucfirst($status) }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex" style="gap:10px;">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                        <a href="{{ route('admin.users.index') }}"
                           class="btn btn-secondary flex-fill">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
document.querySelectorAll('.status-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.status-option').forEach(opt => {
            opt.style.borderColor = '#E2E8F0';
            opt.style.background = '#F8FAFC';
        });
        this.nextElementSibling.style.borderColor = '#0F4CDB';
        this.nextElementSibling.style.background = '#EEF2FF';
    });
    if (this.checked) this.dispatchEvent(new Event('change'));
});
</script>
@endsection
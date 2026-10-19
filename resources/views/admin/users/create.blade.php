@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Create User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Create New User</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Add a new account and assign a role
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
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-user-plus mr-2" style="color:#0F4CDB;"></i>
                Account Information
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.users.store') }}">
                    @csrf

                    <div class="form-group">
                        <label>Full Name</label>
                        <div style="position:relative;">
                            <i class="fas fa-user" style="position:absolute; left:14px;
                               top:50%; transform:translateY(-50%); color:#94A3B8; font-size:13px;"></i>
                            <input type="text" name="name" class="form-control"
                                   style="padding-left:38px;"
                                   value="{{ old('name') }}"
                                   placeholder="Enter full name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div style="position:relative;">
                            <i class="fas fa-envelope" style="position:absolute; left:14px;
                               top:50%; transform:translateY(-50%); color:#94A3B8; font-size:13px;"></i>
                            <input type="email" name="email" class="form-control"
                                   style="padding-left:38px;"
                                   value="{{ old('email') }}"
                                   placeholder="Enter email address" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Temporary Password</label>
                        <div style="position:relative;">
                            <i class="fas fa-lock" style="position:absolute; left:14px;
                               top:50%; transform:translateY(-50%); color:#94A3B8; font-size:13px;"></i>
                            <input type="text" name="password" class="form-control"
                                   style="padding-left:38px;"
                                   placeholder="Set a temporary password" required>
                        </div>
                        <small style="color:#94A3B8; font-size:11px;">
                            Minimum 6 characters. User should change this after first login.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Assign Role</label>
                        <div class="row">
                            @foreach($roles as $role)
                            @php
                                $roleColors = [
                                    'registrar'     => ['bg'=>'#DCFCE7','color'=>'#15803D','icon'=>'fa-id-card'],
                                    'cashier'       => ['bg'=>'#FEF9C3','color'=>'#A16207','icon'=>'fa-cash-register'],
                                    'student'       => ['bg'=>'#EDE9FE','color'=>'#7C3AED','icon'=>'fa-user-graduate'],
                                    'alumni'        => ['bg'=>'#FEE2E2','color'=>'#DC2626','icon'=>'fa-user-tie'],
                                    'new_applicant' => ['bg'=>'#DBEAFE','color'=>'#1D4ED8','icon'=>'fa-user-clock'],
                                ];
                                $rc = $roleColors[$role->name] ?? ['bg'=>'#EEF2FF','color'=>'#0F4CDB','icon'=>'fa-user'];
                            @endphp
                            <div class="col-6 mb-2">
                                <label style="cursor:pointer; width:100%;">
                                    <input type="radio" name="role" value="{{ $role->name }}"
                                           style="display:none;" class="role-radio"
                                           {{ old('role') === $role->name ? 'checked' : '' }}>
                                    <div class="role-option"
                                         style="border:2px solid #E2E8F0; border-radius:12px;
                                                padding:14px; display:flex; align-items:center;
                                                gap:12px; transition:all 0.2s; background:#F8FAFC;">
                                        <div style="width:36px; height:36px; border-radius:10px;
                                                    background:{{ $rc['bg'] }}; display:flex;
                                                    align-items:center; justify-content:center;
                                                    flex-shrink:0;">
                                            <i class="fas {{ $rc['icon'] }}"
                                               style="font-size:14px; color:{{ $rc['color'] }};"></i>
                                        </div>
                                        <div>
                                            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                                {{ ucfirst(str_replace('_',' ', $role->name)) }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex" style="gap:10px;">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-user-plus mr-1"></i> Create Account
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
document.querySelectorAll('.role-radio').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.role-option').forEach(opt => {
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
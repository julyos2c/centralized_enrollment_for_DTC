@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Create User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Create New User</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Add a new account and assign a role
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to Users
        </a>
    </div>
@endsection

@section('content')
<div class="d-flex flex-column mb-3 u-gap-10" >
    <div>
        <h4 class="mb-0 font-weight-bold dtc-subjects-title u-text" >
            Subjects
            @if($selectedProgram)
                — {{ $selectedProgram->name }} ({{ $selectedProgram->code }})
            @endif
        </h4>
        @if(!$selectedProgram)
            <p class="mb-0 u-text-secondary-sm" >
                Select a program above to manage its subjects.
            </p>
        @endif
    </div>
    @if($selectedProgram)
    <div>
        <button type="button" class="dtc-btn dtc-btn-primary" data-toggle="modal" data-target="#addSubjectModal">
            <i data-lucide="plus"></i> Add Subject
        </button>
    </div>
    @endif
</div>
 
<style>
/* Shrinks with the viewport instead of wrapping to 2 lines and pushing
   the Add Subject button around - heading always stays on one line,
   button always stays put on the left below it. */
.dtc-subjects-title {
    font-size: clamp(15px, 4.2vw, 22px);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
@endsection

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header font-weight-bold u-text" >
                <i data-lucide="user-plus" class="mr-2 u-link"></i>
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
                        <div  class="u-relative">
                            <i data-lucide="user" style="position:absolute; left:14px;
                               top:13px; color:var(--dtc-text-muted); font-size:13px; pointer-events:none;"></i>
                            <input type="text" name="name" class="form-control u-pl-38"
                                   
                                   value="{{ old('name') }}"
                                   placeholder="Enter full name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div  class="u-relative">
                            <i data-lucide="mail" style="position:absolute; left:14px;
                               top:13px; color:var(--dtc-text-muted); font-size:13px; pointer-events:none;"></i>
                            <input type="email" name="email" class="form-control u-pl-38"
                                   
                                   value="{{ old('email') }}"
                                   placeholder="Enter email address" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Temporary Password</label>
                        <div  class="u-relative">
                            <i data-lucide="lock" style="position:absolute; left:14px;
                               top:13px; color:var(--dtc-text-muted); font-size:13px; pointer-events:none;"></i>
                            <input type="text" name="password" class="form-control u-pl-38"
                                   
                                   placeholder="Set a temporary password" required>
                        </div>
                        <small style="color:var(--dtc-text-muted); font-size:11px;">
                            Minimum 6 characters. User should change this after first login.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Assign Role</label>
                        <div class="row">
                            @foreach($roles as $role)
                            @php
                                $roleColors = [
                                    'registrar'     => ['bg'=>'#DCFCE7','color'=>'#15803D','icon'=>'id-card'],
                                    'cashier'       => ['bg'=>'#FEF9C3','color'=>'#A16207','icon'=>'receipt'],
                                    'student'       => ['bg'=>'#EDE9FE','color'=>'#7C3AED','icon'=>'graduation-cap'],
                                    'alumni'        => ['bg'=>'#FEE2E2','color'=>'#DC2626','icon'=>'award'],
                                    'new_applicant' => ['bg'=>'#DBEAFE','color'=>'#1D4ED8','icon'=>'user-plus'],
                                ];
                                $rc = $roleColors[$role->name] ?? ['bg'=>'#EEF2FF','color'=>'#0F4CDB','icon'=>'user'];
                            @endphp
                            <div class="col-6 mb-2">
                                <label style="cursor:pointer; width:100%;">
                                    <input type="radio" name="role" value="{{ $role->name }}"
                                           style="display:none;" class="role-radio"
                                           {{ old('role') === $role->name ? 'checked' : '' }}>
                                    <div class="role-option"
                                         style="border:2px solid var(--dtc-border); border-radius:12px;
                                                padding:14px; display:flex; align-items:center;
                                                gap:12px; transition:all 0.2s; background:var(--dtc-surface-soft);">
                                        <div class="dtc-icon-swatch is-{{ $rc['tone'] }}" style="width:36px;height:36px;flex-shrink:0;">
                                            <i data-lucide="{{ $rc['icon'] }}" style="font-size:14px;"></i>
                                        </div>
                                        <div>
                                            <div  class="u-text-sm-bold-primary">
                                                {{ ucfirst(str_replace('_',' ', $role->name)) }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex u-gap-10" >
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i data-lucide="user-plus" class="mr-1"></i> Create Account
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
            opt.style.borderColor = 'var(--dtc-border)';
            opt.style.background = 'var(--dtc-surface-soft)';
        });
        this.nextElementSibling.style.borderColor = '#0F4CDB';
        this.nextElementSibling.style.background = '#EEF2FF';
    });
    if (this.checked) this.dispatchEvent(new Event('change'));
});
</script>
@endsection
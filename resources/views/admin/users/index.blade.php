@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Users')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Manage Users</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Create and manage system user accounts
            </p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus mr-1"></i> Add New User
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Stats Row --}}
<div class="row mb-3">
    @php
        $roles = ['admin','registrar','cashier','student','alumni','new_applicant'];
        $colors = ['stat-blue','stat-green','stat-yellow','stat-purple','stat-red','stat-blue'];
        $icons  = ['fa-shield-alt','fa-id-card','fa-cash-register','fa-user-graduate','fa-user-tie','fa-user-clock'];
    @endphp
    @foreach($roles as $i => $role)
    <div class="col-lg-2 col-4 mb-3">
        <div class="card" style="border-radius:14px; padding:16px; text-align:center;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); border:none;">
            <div style="font-size:22px; font-weight:800; color:#1E293B;">
                {{ \App\Models\User::role($role)->count() }}
            </div>
            <div style="font-size:11px; color:#64748B; font-weight:600; margin-top:2px;
                        text-transform:capitalize;">
                {{ str_replace('_',' ', $role) }}
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold" style="color:#1E293B;">All Users</span>
        <span style="font-size:13px; color:#64748B;">
            {{ $users->count() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
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
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:38px; height:38px; border-radius:50%;
                                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:14px; flex-shrink:0;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                    {{ $user->name }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px; color:#64748B;">{{ $user->email }}</td>
                    <td>
                        @foreach($user->roles as $role)
                            @php
                                $roleColors = [
                                    'admin'         => ['bg'=>'#EEF2FF','color'=>'#0F4CDB'],
                                    'registrar'     => ['bg'=>'#DCFCE7','color'=>'#15803D'],
                                    'cashier'       => ['bg'=>'#FEF9C3','color'=>'#A16207'],
                                    'student'       => ['bg'=>'#EDE9FE','color'=>'#7C3AED'],
                                    'alumni'        => ['bg'=>'#FEE2E2','color'=>'#DC2626'],
                                    'new_applicant' => ['bg'=>'#DBEAFE','color'=>'#1D4ED8'],
                                ];
                                $rc = $roleColors[$role->name] ?? ['bg'=>'#F1F5F9','color'=>'#64748B'];
                            @endphp
                            <span style="background:{{ $rc['bg'] }}; color:{{ $rc['color'] }};
                                         font-size:11px; font-weight:700; padding:3px 10px;
                                         border-radius:20px; text-transform:capitalize;">
                                {{ str_replace('_',' ', $role->name) }}
                            </span>
                        @endforeach
                    </td>
                    <td>
                        @if($user->status === 'active')
                            <span style="background:#DCFCE7; color:#15803D; font-size:11px;
                                         font-weight:700; padding:3px 10px; border-radius:20px;">
                                Active
                            </span>
                        @elseif($user->status === 'locked')
                            <span style="background:#FEE2E2; color:#DC2626; font-size:11px;
                                         font-weight:700; padding:3px 10px; border-radius:20px;">
                                Locked
                            </span>
                        @else
                            <span style="background:#FEF9C3; color:#A16207; font-size:11px;
                                         font-weight:700; padding:3px 10px; border-radius:20px;">
                                Pending
                            </span>
                        @endif
                    </td>
                    <td style="font-size:12px; color:#94A3B8;">
                        {{ $user->created_at->format('M d, Y') }}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               style="background:#EEF2FF; color:#0F4CDB; border:none;
                                      padding:5px 12px; border-radius:8px; font-size:11px;
                                      font-weight:600; text-decoration:none;">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                            @if($user->id !== auth()->id())
                            <form method="POST"
                                  action="{{ route('admin.users.destroy', $user) }}">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        style="background:#FEE2E2; color:#DC2626; border:none;
                                               padding:5px 12px; border-radius:8px; font-size:11px;
                                               font-weight:600; cursor:pointer;"
                                        onclick="return confirm('Delete {{ $user->name }}?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5" style="color:#94A3B8;">
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
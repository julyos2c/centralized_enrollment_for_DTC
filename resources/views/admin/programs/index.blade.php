@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Programs')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Programs & Subjects</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Manage academic programs and their subjects</p>
        </div>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
    {{-- Add Program Form --}}
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-plus-circle mr-2" style="color:#0F4CDB;"></i> Add Program
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.programs.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Program Name</label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g. BS Information Systems"
                               value="{{ old('name') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Program Code</label>
                        <input type="text" name="code" class="form-control"
                               placeholder="e.g. BSIS"
                               value="{{ old('code') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-plus mr-1"></i> Add Program
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Programs List --}}
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                All Programs
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>Code</th>
                            <th>Subjects</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($programs as $program)
                        <tr>
                            <td>
                                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                    {{ $program->name }}
                                </div>
                            </td>
                            <td>
                                <span style="background:#EEF2FF; color:#0F4CDB; font-size:12px;
                                             font-weight:700; padding:3px 10px; border-radius:20px;">
                                    {{ $program->code }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:13px; font-weight:600; color:#1E293B;">
                                    {{ $program->subjects_count }}
                                </span>
                                <span style="font-size:12px; color:#94A3B8;"> subjects</span>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="{{ route('admin.programs.subjects', $program) }}"
                                       style="background:#EEF2FF; color:#0F4CDB; border:none;
                                              padding:5px 12px; border-radius:8px; font-size:11px;
                                              font-weight:600; text-decoration:none;">
                                        <i class="fas fa-book mr-1"></i> Subjects
                                    </a>
                                    <form method="POST"
                                          action="{{ route('admin.programs.destroy', $program) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                style="background:#FEE2E2; color:#DC2626; border:none;
                                                       padding:5px 12px; border-radius:8px;
                                                       font-size:11px; font-weight:600; cursor:pointer;"
                                                onclick="return confirm('Delete {{ $program->name }}?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4" style="color:#94A3B8;">
                                No programs yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
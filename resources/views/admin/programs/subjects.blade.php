@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Subjects')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">
                Subjects — {{ $program->name }}
            </h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Manage subjects for {{ $program->code }}
            </p>
        </div>
        <a href="{{ route('admin.programs.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Programs
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
    {{-- Add Subject Form --}}
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-plus-circle mr-2" style="color:#0F4CDB;"></i> Add Subject
            </div>
            <div class="card-body">
                <form method="POST"
                      action="{{ route('admin.programs.subjects.store', $program) }}">
                    @csrf
                    <div class="form-group">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" class="form-control"
                               placeholder="e.g. IS101"
                               value="{{ old('subject_code') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Subject Name</label>
                        <input type="text" name="subject_name" class="form-control"
                               placeholder="e.g. Introduction to Computing"
                               value="{{ old('subject_name') }}" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Year Level</label>
                                <select name="year_level" class="form-control" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" class="form-control" required>
                                    <option value="1">1st Sem</option>
                                    <option value="2">2nd Sem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-plus mr-1"></i> Add Subject
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Subjects List --}}
    <div class="col-lg-8 mb-3">
        @foreach([1,2,3,4] as $year)
            @foreach([1,2] as $sem)
                @php
                    $filtered = $subjects->where('year_level', $year)->where('semester', $sem);
                @endphp
                @if($filtered->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"
                         style="background:#F8FAFC; font-size:12px; font-weight:700;
                                color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">
                        Year {{ $year }} — Semester {{ $sem }}
                        <span style="float:right; color:#0F4CDB;">
                            {{ $filtered->count() }} subjects
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Subject Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($filtered as $subject)
                                <tr>
                                    <td>
                                        <span style="background:#EEF2FF; color:#0F4CDB;
                                                     font-size:12px; font-weight:700;
                                                     padding:3px 10px; border-radius:20px;">
                                            {{ $subject->subject_code }}
                                        </span>
                                    </td>
                                    <td style="font-size:13px; color:#1E293B;">
                                        {{ $subject->subject_name }}
                                    </td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('admin.programs.subjects.destroy', $subject) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    style="background:#FEE2E2; color:#DC2626;
                                                           border:none; padding:4px 10px;
                                                           border-radius:8px; font-size:11px;
                                                           font-weight:600; cursor:pointer;"
                                                    onclick="return confirm('Remove this subject?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            @endforeach
        @endforeach

        @if($subjects->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5" style="color:#94A3B8;">
                <i class="fas fa-book fa-3x mb-3"></i>
                <p>No subjects added yet. Add subjects using the form.</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
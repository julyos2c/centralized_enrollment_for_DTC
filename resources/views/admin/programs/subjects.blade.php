@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Subjects')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >
                Subjects — {{ $program->name }}
            </h4>
            <p class="mb-0 u-text-secondary-sm" >
                Manage subjects for {{ $program->code }}
            </p>
        </div>
        <a href="{{ route('admin.programs.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to Programs
        </a>
    </div>
@endsection

@section('content')


<div class="row">
    {{-- Add Subject Form --}}
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold u-text" >
                <i data-lucide="plus-circle" class="mr-2 u-link"></i> Add Subject
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
                        <i data-lucide="plus" class="mr-1"></i> Add Subject
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
                         style="background:var(--dtc-surface-soft); font-size:12px; font-weight:700;
                                color:var(--dtc-text-secondary); text-transform:uppercase; letter-spacing:0.5px;">
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
                                    <td  class="u-text-sm-secondary-primary">
                                        {{ $subject->subject_name }}
                                    </td>
                                    <td>
                                        <form id="del-subject-{{ $subject->id }}"
                                              method="POST"
                                              action="{{ route('admin.programs.subjects.destroy', $subject) }}">
                                            @csrf @method('DELETE')
                                            <button type="button"
                                                    class="dtc-icon-btn danger"
                                                    data-dtc-confirm
                                                    data-dtc-confirm-title="Remove Subject?"
                                                    data-dtc-confirm-message="Are you sure you want to remove this subject from the program?"
                                                    data-dtc-confirm-ok="Remove Subject"
                                                    data-dtc-confirm-form="#del-subject-{{ $subject->id }}">
                                                <i data-lucide="trash-2"></i>
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
            <div class="card-body text-center py-5 u-text-muted" >
                <i data-lucide="book" class="mb-3" style="width:3em;height:3em"></i>
                <p>No subjects added yet. Add subjects using the form.</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
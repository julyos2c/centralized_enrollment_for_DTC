@extends('adminlte::page')

@section('title', 'Pending Enrollments')

@section('content_header')
    <h1>Pending Enrollments</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Program</th>
                    <th>Year / Semester</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->user->name }}</td>
                    <td>{{ $enrollment->program->name }}</td>
                    <td>Year {{ $enrollment->year_level }} - Sem {{ $enrollment->semester }}</td>
                    <td>{{ $enrollment->created_at->format('M d, Y h:i A') }}</td>
                    <td>
                        <a href="{{ route('registrar.enrollments.show', $enrollment) }}" class="btn btn-sm btn-primary">Review</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">No pending enrollments.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
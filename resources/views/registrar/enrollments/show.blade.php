@extends('adminlte::page')

@section('title', 'Review Enrollment')

@section('content_header')
    <h1>Review Enrollment</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">

        <h5>Applicant Information</h5>
        <p><strong>Name:</strong> {{ $enrollment->user->name }}</p>
        <p><strong>Email:</strong> {{ $enrollment->user->email }}</p>

        <hr>

        <h5>Enrollment Details</h5>
        <p><strong>Program:</strong> {{ $enrollment->program->name }}</p>
        <p><strong>Year Level:</strong> {{ $enrollment->year_level }}</p>
        <p><strong>Semester:</strong> {{ $enrollment->semester }}</p>

        <h5>Selected Subjects</h5>
        <ul>
            @foreach ($subjects as $subject)
                <li>{{ $subject->subject_code }} - {{ $subject->subject_name }}</li>
            @endforeach
        </ul>

        <hr>

        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('registrar.enrollments.approve', $enrollment) }}">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Approve this enrollment?')">
                    Approve
                </button>
            </form>

            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal">
                Reject
            </button>
        </div>

        <a href="{{ route('registrar.enrollments.index') }}" class="btn btn-secondary mt-2">← Back to List</a>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.enrollments.reject', $enrollment) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Enrollment</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label>Reason / Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Academic Record')

@section('content_header')
    <div class="ar-page-heading">
        <div>
            <h1>Academic Record</h1>
            <p>Record {{ $record->student_number }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('registrar.students.edit', $record) }}">Edit Record</a>
    </div>
@endsection

@section('content')
    @php
        $fullName = trim(implode(' ', array_filter([
            $record->first_name,
            $record->middle_name,
            $record->last_name,
            $record->suffix,
        ])));
        $details = [
            'Student Number' => $record->student_number,
            'Name' => $fullName ?: ($record->user->name ?? 'Not recorded'),
            'Email' => $record->email ?: 'Not recorded',
            'Program' => $record->program->name ?? 'Not recorded',
            'Year Level' => $record->year_level ?: 'Not recorded',
            'School Year' => $record->enrolled_ay ?: 'Not recorded',
            'Academic Status' => ucfirst($record->academic_status),
            'Graduation Year' => $record->graduation_year ?: 'Not recorded',
            'Graduation Date' => optional($record->graduation_date)->format('Y-m-d') ?: 'Not recorded',
            'Portal Account' => $record->user ? 'Linked' : 'No portal account',
        ];
    @endphp

    <section class="card ar-record-card" aria-label="Student academic details">
        <div class="card-body">
            <dl class="ar-record-grid">
                @foreach ($details as $label => $value)
                    <div class="ar-record-field">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($enrollments->isNotEmpty())
                <section class="ar-history-section">
                    <h2>Enrollment History</h2>
                    <div class="table-responsive">
                        <table class="table table-sm ar-history-table">
                            <thead><tr><th>School Year</th><th>Semester</th><th>Program</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($enrollments as $enrollment)
                                    <tr>
                                        <td>{{ $enrollment->school_year }}</td>
                                        <td>{{ ucfirst($enrollment->semester) }}</td>
                                        <td>{{ $enrollment->program->name ?? '—' }}</td>
                                        <td>{{ ucfirst($enrollment->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($documentRequests->isNotEmpty())
                <section class="ar-history-section">
                    <h2>Certificate and Document Requests</h2>
                    <div class="table-responsive">
                        <table class="table table-sm ar-history-table">
                            <thead><tr><th>Document</th><th>Requested</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($documentRequests as $documentRequest)
                                    <tr>
                                        <td>{{ $documentRequest->document_label }}</td>
                                        <td>{{ $documentRequest->created_at->format('M j, Y') }}</td>
                                        <td>{{ ucfirst($documentRequest->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <a class="btn btn-light mt-3" href="{{ route('registrar.students.index', ['type' => $record->academic_status === 'alumni' ? 'alumni' : 'students']) }}">Back to records</a>
        </div>
    </section>
@endsection

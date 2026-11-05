@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Enrollment Status')

@section('content_header')
    <div class="dtc-enrollment-index-header">
        <div class="dtc-enrollment-index-heading">
            <h1>Enrollment Status</h1>
            <p>Your current enrollment and payment progress.</p>
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/enrollment-status.css') }}">
@endpush
@section('content')
<div class="dtc-enrollment-status-page">

@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

@if ($enrollments->isEmpty())
    <div class="card dtc-enrollment-empty">
        <div class="card-body text-center py-5">
            <i data-lucide="file-text" class="dtc-enrollment-empty-icon mb-3" aria-hidden="true"></i>
            <h2>No Enrollments Yet</h2>
            <p>
                You haven't submitted any enrollment yet.
            </p>
            <a href="{{ route('portal.enrollment.create') }}" class="dtc-btn dtc-btn-primary dtc-enrollment-empty-action">
                <i data-lucide="plus" aria-hidden="true"></i> Enroll Now
            </a>
        </div>
    </div>
@else
    @foreach ($enrollments as $enrollment)
        @include('student.enrollment._status-card', ['enrollment' => $enrollment])
    @endforeach

    @if ($enrollments->hasPages())
    <div class="dtc-enrollment-pagination">
        <small  class="u-text-secondary">
            Showing
            <strong>{{ $enrollments->firstItem() }}</strong>
            to
            <strong>{{ $enrollments->lastItem() }}</strong>
            of
            <strong>{{ $enrollments->total() }}</strong>
            results
        </small>
        <div>
            {{ $enrollments->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
    </div>
    @endif
@endif



</div>
@endsection

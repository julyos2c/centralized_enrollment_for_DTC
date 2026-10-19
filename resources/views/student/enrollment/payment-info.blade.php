@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Payment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Enrollment Payment</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Complete your official enrollment payment
            </p>
        </div>
        <a href="{{ route('portal.enrollment.index') }}" class="btn btn-outline-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to Enrollment Status
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success" role="status"><i data-lucide="circle-check" class="mr-2"></i>{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert" class="mr-2"></i>{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="dtc-card dtc-review-standalone">
    @include('student.enrollment._payment-content')
</div>

@endsection

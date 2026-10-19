@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Official Receipt')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Official Receipt</h4>
            <p class="mb-0 u-text-secondary-sm" >Enrollment payment confirmation</p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back to Payments
        </a>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            @include('cashier.payments._receipt-content', ['enrollment' => $enrollment, 'payment' => $payment])
        </div>
    </div>
</div>

@include('cashier.payments._receipt-modal')

@endsection

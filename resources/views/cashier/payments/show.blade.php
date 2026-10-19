@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Process Payment</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Confirm and record enrollment fee collection
            </p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="mr-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            @include('cashier.payments._payment-content', ['enrollment' => $enrollment, 'latestPayment' => $latestPayment])
        </div>
    </div>
</div>
@endsection
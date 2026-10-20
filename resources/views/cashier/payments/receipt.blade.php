@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Official Receipt')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center dtc-receipt-page-heading">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Official Receipt</h4>
            <p class="mb-0 u-text-secondary-sm" >Enrollment payment confirmation</p>
        </div>
        <div class="dtc-receipt-page-actions">
            <button type="button" class="btn btn-primary btn-sm" onclick="window.printReceiptModal()">
                <i data-lucide="printer" class="mr-1" aria-hidden="true"></i> Print Receipt
            </button>
            <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary btn-sm">
                <i data-lucide="arrow-left" class="mr-1" aria-hidden="true"></i> Back to Payments
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card dtc-receipt-standalone">
            @include('cashier.payments._receipt-content', ['enrollment' => $enrollment, 'payment' => $payment])
        </div>
    </div>
</div>

@include('cashier.payments._receipt-modal')

@endsection

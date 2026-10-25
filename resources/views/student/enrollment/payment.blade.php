@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Enrollment payment')
@push('css')
<link rel="stylesheet" href="{{ asset('css/portal-payments.css') }}">
@endpush
@section('content')
<main class="portal-payment-page">
    <a class="portal-payment-back" href="{{ route('portal.enrollment.index') }}">← Back to enrollment status</a>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($enrollment->status !== 'approved' && !$enrollment->is_paid)
        <section class="portal-payment-card"><h1>Enrollment payment</h1><p>Payment is available after the Registrar approves your enrollment.</p></section>
    @else
        @include('student.enrollment._payment-content')
    @endif
</main>
@endsection

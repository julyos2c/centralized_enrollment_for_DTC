@extends('adminlte::page')
@section('title', $title)
@section('content_header')
<div class="content">
    <x-dtc.page-header title="Help Center" subtitle="Quick answers for enrollment, requirements, appointments, and payments." icon="circle-help" />
</div>
@stop

@section('content')
<div class="content">
<div class="row">
@foreach([
 ['How do I enroll?','Open Enrollment and follow the guided steps until your enrollment is submitted.','fa-file-signature'],
 ['How do I upload requirements?','Use the Requirements page and upload each required document in PDF, JPG, or PNG format.','fa-upload'],
 ['How do I pay?','Once your enrollment is approved, open Payments and submit your GCash reference and proof.','fa-credit-card'],
 ['Need help from staff?','Use your Notifications page for system updates or contact the Registrar/Cashier office.','fa-headset'],
] as $item)
<div class="col-lg-6 mb-3"><div class="card h-100 mb-0"><div class="card-body d-flex gap-3"><div class="dtc-stat-icon"><i data-lucide="{{ $item[2] }}"></i></div><div><div class="font-weight-bold">{{ $item[0] }}</div><div class="text-muted small mt-1">{{ $item[1] }}</div></div></div></div></div>
@endforeach
</div>
<div class="card"><div class="card-body"><strong>Still need assistance?</strong><div class="text-muted small mt-1">Please contact the DTC Registrar's Office for application and enrollment concerns, or the Cashier's Office for payment verification.</div></div></div>
</div>
@stop

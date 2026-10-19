@extends('adminlte::page')
@section('title', $title)
@section('content_header')
<div class="content">
    <x-dtc.page-header title="Enrollment Steps" subtitle="A clear view of your path from application to official enrollment." icon="list-ordered" />
</div>
@stop

@section('content')
<div class="content">
@php
$steps = [
 ['title'=>'Submit Application','desc'=>'Complete your pre-enrollment information and supporting documents.','done'=>(bool) $application],
 ['title'=>'Registrar Review','desc'=>'Your application and requirements are checked by the Registrar.','done'=>$application && in_array($application->status,['approved'])],
 ['title'=>'Official Enrollment','desc'=>'Submit your official enrollment and selected subjects.','done'=>(bool)$enrollment],
 ['title'=>'Enrollment Approval','desc'=>'Wait for the Registrar to review your enrollment.','done'=>$enrollment && $enrollment->status==='approved'],
 ['title'=>'Payment','desc'=>'Submit the required payment and proof for Cashier verification.','done'=>$enrollment && $enrollment->is_paid],
 ['title'=>'Enrollment Completed','desc'=>'Your account is fully enrolled for the current term.','done'=>$enrollment && $enrollment->is_paid],
];
@endphp
<div class="card"><div class="card-body"><div class="dtc-timeline">
@foreach($steps as $i=>$step)
<div class="dtc-timeline-item {{ $step['done'] ? 'is-done' : ($i === 0 || ($i>0 && !$steps[$i-1]['done']) ? 'is-active' : '') }}">
<div class="dtc-timeline-dot"><i data-lucide="{{ $step['done'] ? 'check' : 'circle' }}"></i></div>
<div><div class="dtc-timeline-title">{{ $step['title'] }}</div><div class="dtc-timeline-subtitle">{{ $step['desc'] }}</div></div>
</div>
@endforeach
</div></div></div>
</div>
@stop

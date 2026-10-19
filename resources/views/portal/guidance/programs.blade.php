@extends('adminlte::page')
@section('title', $title)
@section('content_header')
<div class="content">
    <x-dtc.page-header title="Programs" subtitle="Explore the academic programs available in DTC Nexus." icon="graduation-cap" />
</div>
@stop

@section('content')
<div class="content">
<div class="row">
@forelse($programs as $program)
<div class="col-xl-4 col-md-6 mb-3"><div class="card h-100 mb-0"><div class="card-body">
<div class="dtc-stat-icon mb-3"><i data-lucide="graduation-cap"></i></div>
<h5 class="font-weight-bold mb-1">{{ $program->name }}</h5>
<div class="text-muted small">{{ $program->description ?? 'Academic program offered by Danao Technological College.' }}</div>
</div></div></div>
@empty
<div class="col-12"><x-dtc.empty-state icon="graduation-cap" title="No programs available" message="Programs will appear here once the academic catalog is configured." /></div>
@endforelse
</div>
</div>
@stop

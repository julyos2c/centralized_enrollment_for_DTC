@extends('adminlte::page')
@section('title', $title)
@section('content_header')
<div class="content">
    <x-dtc.page-header title="Requirements" subtitle="Track the documents required for your enrollment application." icon="folder-open" />
</div>
@stop

@section('content')
<div class="content">
<div class="row">
    @forelse($required as $key => $label)
        @php $doc = $documents->get($key); @endphp
        <div class="col-lg-6 mb-3">
            <div class="card h-100 mb-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="dtc-stat-icon {{ $doc ? 'is-success' : 'is-warning' }}"><i data-lucide="{{ $doc ? 'check' : 'file-up' }}"></i></div>
                    <div class="flex-grow-1">
                        <div class="font-weight-bold">{{ $label }}</div>
                        <div class="text-muted small mt-1">{{ $doc ? $doc->original_name : 'Not uploaded yet' }}</div>
                    </div>
                    @if($doc)<x-dtc.status-badge status="completed" />@else<x-dtc.status-badge status="pending" />@endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><x-dtc.empty-state icon="folder-open" title="No requirements configured" /></div>
    @endforelse
</div>
@if($application)
<div class="card mt-2"><div class="card-body"><div class="d-flex justify-content-between align-items-center flex-wrap"><div><strong>Application {{ $application->reference_no }}</strong><div class="text-muted small mt-1">{{ $application->program->name ?? 'Program not specified' }}</div></div><x-dtc.status-badge status="{{ $application->status }}" /></div></div></div>
@else
<div class="card"><div class="card-body"><x-dtc.empty-state icon="file-text" title="No application linked to this account" message="Please contact the Registrar if you expect an application to be available." /></div></div>
@endif
</div>
@stop

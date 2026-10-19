@extends('adminlte::page')
@section('title', $title)
@section('content_header')
<div class="content">
    <x-dtc.page-header title="Announcements" subtitle="Your latest DTC Nexus updates and notices." icon="megaphone" />
</div>
@stop

@section('content')
<div class="content">
@forelse($announcements as $announcement)
<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-3"><div><div class="font-weight-bold">{{ $announcement->title }}</div><div class="text-muted small mt-2">{{ $announcement->message }}</div></div><div class="text-muted small">{{ $announcement->created_at->diffForHumans() }}</div></div></div></div>
@empty
<x-dtc.empty-state icon="megaphone" title="No announcements yet" message="System announcements will appear here." />
@endforelse
<div class="mt-3">{{ $announcements->links() }}</div>
</div>
@stop

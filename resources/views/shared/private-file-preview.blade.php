@extends('adminlte::page')
@include('partials.navbar')

@section('title', $title)

@section('css')
<link rel="stylesheet" href="{{ asset('css/private-file-preview.css') }}">
@endsection

@section('content')
<main class="private-file-preview">
    <header class="private-file-preview-header">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $fileName }}</p>
        </div>
        <nav aria-label="File actions">
            <a class="private-file-preview-back" href="{{ $backUrl }}">← Back to details</a>
            <a class="private-file-preview-download" href="{{ $downloadUrl }}">Download</a>
        </nav>
    </header>
    @if ($isImage)
        <div class="private-file-preview-image-frame">
            <img src="{{ $previewUrl }}" alt="{{ $title }}: {{ $fileName }}" class="private-file-preview-image">
        </div>
    @else
        <iframe src="{{ $previewUrl }}" title="{{ $title }}" class="private-file-preview-frame"></iframe>
    @endif
</main>
@endsection

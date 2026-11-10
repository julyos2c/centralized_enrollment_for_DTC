@extends('layouts.public')

@section('title', $document === 'terms' ? 'Terms and Conditions' : 'Privacy Notice')

@section('styles')
<link rel="stylesheet" href="{{ asset_v('css/legal.css') }}">
@stop

@section('content')
<div class="legal-page">
    <nav class="legal-page-nav" aria-label="Policy documents">
        <a href="{{ route('legal.terms') }}" @if($document === 'terms') aria-current="page" @endif>Terms and Conditions</a>
        <a href="{{ route('legal.privacy') }}" @if($document === 'privacy') aria-current="page" @endif>Privacy Notice</a>
    </nav>
    <div class="legal-page-body">
        @include('legal.' . $document)
    </div>
</div>
@endsection

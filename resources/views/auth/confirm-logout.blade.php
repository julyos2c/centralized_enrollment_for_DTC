@extends('layouts.public')

@section('title', 'Confirm logout')

@section('content')
<div class="container py-5">
    <div class="card mx-auto" style="max-width:440px">
        <div class="card-body p-4">
            <h1 class="h4">Log out?</h1>
            <p>Your page was out of date. Confirm below to securely log out.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <div class="d-flex gap-2">
                    <a href="{{ route('login') }}" class="btn btn-secondary flex-fill">Stay signed in</a>
                    <button type="submit" class="btn btn-primary flex-fill">Log out</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@extends('layouts.public')
@section('title', 'Change temporary password')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/required-password.css') }}">
@endsection
@section('content')
<div class="required-password-page">
<section class="required-password-card" aria-labelledby="required-password-title">
    <header>
        <p class="required-password-eyebrow">Account setup</p>
        <h1 id="required-password-title">Set a new password</h1>
        <p>Replace your temporary password to continue.</p>
    </header>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('account.password.update') }}" data-password-distinct data-password-icon-toggles>
        @csrf
        <div class="form-group">
            <label for="temporary-password">Temporary password</label>
            <div class="required-password-control"><input id="temporary-password" name="current_password" type="password" class="form-control" autocomplete="current-password" required></div>
        </div>
        <div class="form-group">
            <label for="required-password">New password</label>
            <div class="required-password-control"><input id="required-password" name="password" type="password" class="form-control" autocomplete="new-password" minlength="8" required></div>
        </div>
        <div class="form-group">
            <label for="required-confirmation">Confirm new password</label>
            <div class="required-password-control"><input id="required-confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" minlength="8" required></div>
        </div>
        <button class="btn btn-primary required-password-submit" type="submit">Save password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="required-password-exit">@csrf<button type="submit">Log out</button></form>
</section>
</div>
@endsection

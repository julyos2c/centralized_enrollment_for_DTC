@extends('layouts.public')
@section('title', 'Change temporary password')
@section('content')
<div class="container py-5" style="max-width:520px">
    <h1 class="h4">Change your temporary password</h1>
    <p>Choose a new password before continuing to your account.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('account.password.update') }}">
        @csrf
        <label for="temporary-password">Temporary password</label>
        <input id="temporary-password" name="current_password" type="password" class="form-control mb-3" autocomplete="current-password" required>
        <label for="required-password">New password</label>
        <input id="required-password" name="password" type="password" class="form-control mb-3" autocomplete="new-password" minlength="8" required>
        <label for="required-confirmation">Confirm new password</label>
        <input id="required-confirmation" name="password_confirmation" type="password" class="form-control mb-3" autocomplete="new-password" minlength="8" required>
        <button class="btn btn-primary" type="submit">Change password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn btn-secondary" type="submit">Log out</button></form>
</div>
@endsection

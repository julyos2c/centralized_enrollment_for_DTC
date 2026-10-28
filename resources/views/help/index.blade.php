@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Help Center')
@section('content')
<div class="help-center">
    <header class="help-heading">
        <h1>Help Center</h1>
        <p>Clear answers for your next step.</p>
        <a class="help-contact-jump" href="#help-contact-title">Contact the Registrar <span aria-hidden="true">↗</span></a>
    </header>
    <form method="GET" action="{{ route('help.index') }}" class="help-search" role="search">
        <label for="help-query">Search help topics</label>
        @if($category !== '')<input type="hidden" name="category" value="{{ $category }}">@endif
        <div><input type="search" id="help-query" name="q" maxlength="150" value="{{ $query }}" placeholder="Search payments, pickup…">
        <button type="submit" class="btn btn-primary">Search</button></div>
        @error('q')<p class="text-danger">{{ $message }}</p>@enderror
    </form>
    <nav class="help-categories" aria-label="Help topics">
        <a href="{{ route('help.index', ['q' => $query]) }}" @if($category === '') aria-current="page" @endif>All topics</a>
        @foreach($categories as $item)
            <a href="{{ route('help.index', ['q' => $query, 'category' => $item]) }}" @if($category === $item) aria-current="page" @endif>{{ $item }}</a>
        @endforeach
    </nav>
    <div class="help-layout">
        <main>
            <div class="help-results"><h2>{{ $query !== '' ? 'Search results' : ($category ?: 'Common questions') }}</h2><span>{{ $topics->count() }} {{ $topics->count() === 1 ? 'topic' : 'topics' }}</span></div>
            @if($query !== '')<p class="help-query-note">Results for “{{ $query }}” · <a href="{{ route('help.index', ['category' => $category]) }}">Clear search</a></p>@endif
            <div class="help-question-list">
            @forelse($topics as $topic)
                <details class="help-topic">
                    <summary><span class="help-question-copy"><span class="help-category">{{ $topic['category'] }}</span><span>{{ $topic['title'] }}</span></span><span class="help-expand" aria-hidden="true"></span></summary>
                    <div class="help-answer"><p>{{ $topic['answer'] }}</p><a href="{{ route($topic['route']) }}">{{ $topic['label'] }} <span aria-hidden="true">→</span></a></div>
                </details>
            @empty
                <div class="help-empty"><h3>No matching topics</h3><p>Try a shorter phrase or contact the Registrar for help.</p><a href="{{ route('help.index') }}">View all topics</a></div>
            @endforelse
            </div>
        </main>
        <aside class="help-contact" aria-labelledby="help-contact-title">
            <h2 id="help-contact-title">Need more help?</h2>
            <p>Contact the Registrar about enrollment, records, or pickup arrangements. Ask the Cashier’s Office about payment verification.</p>
            <dl>
                @foreach(['registrar_phone' => 'Phone', 'registrar_office' => 'Registrar’s Office', 'registrar_hours' => 'Office hours'] as $key => $label)
                    @if(filled($contact->get($key)))<div><dt>{{ $label }}</dt><dd>{{ $contact->get($key) }}</dd></div>@endif
                @endforeach
                @if($email)<div><dt>Email</dt><dd>{{ $email }}</dd></div>@endif
            </dl>
            @if($email)<a class="btn btn-primary" href="mailto:{{ $email }}">Email Registrar</a>
            @else<p class="help-contact-note">Visit the Registrar’s Office for assistance.</p>@endif
            <p class="help-contact-note">Have your application or booking reference ready. Never share your password.</p>
        </aside>
    </div>
</div>
@endsection
@section('css')
<link rel="stylesheet" href="{{ asset('css/help-center.css') }}">
@endsection

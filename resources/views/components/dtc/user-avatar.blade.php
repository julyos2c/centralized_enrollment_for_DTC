@props(['user' => null, 'name' => null])
@php
    $avatarName = $name ?? $user?->name ?? 'User';
    $avatarInitials = collect(preg_split('/\s+/u', trim($avatarName)))->filter()
        ->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<div {{ $attributes->merge(['class' => 'dtc-user-avatar']) }}>
    <span aria-hidden="true">{{ $avatarInitials ?: 'U' }}</span>
    @if($user?->avatar)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar) }}"
             alt="{{ $avatarName }} profile photo" loading="lazy" onerror="this.hidden = true;">
    @endif
</div>

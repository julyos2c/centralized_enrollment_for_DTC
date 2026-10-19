@props(['title', 'subtitle' => null, 'icon' => null])
<div class="dtc-page-header">
    <div class="dtc-page-header-copy">
        @if($icon)<div class="dtc-page-header-icon"><i data-lucide="{{ $icon }}"></i></div>@endif
        <div><h1 class="dtc-page-title">{{ $title }}</h1>@if($subtitle)<p class="dtc-page-subtitle">{{ $subtitle }}</p>@endif</div>
    </div>
    @isset($actions)<div class="dtc-page-actions">{{ $actions }}</div>@endisset
</div>

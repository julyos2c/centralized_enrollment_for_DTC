@php
    $iconMap = include resource_path('views/vendor/adminlte/partials/sidebar/dtc-icon-map.php');
    $rawIcon = $item['icon'] ?? 'circle';
    $strippedIcon = preg_replace('/\b(fa[srbl]?|fa-fw|fa-\d+x)\s*/i', '', $rawIcon);
    $bareIcon = trim(preg_replace('/^fa-/i', '', trim($strippedIcon)));
    $lucideIcon = $iconMap[$bareIcon] ?? ($bareIcon ?: 'circle');
@endphp
<span class="dtc-sidebar-icon-slot">
    <i data-lucide="{{ $lucideIcon }}" aria-hidden="true" class="nav-icon {{ isset($item['icon_color']) ? 'text-'.$item['icon_color'] : '' }}"></i>
    @if(!empty($item['is_notification']))
        @php $count = max(0, (int) ($item['label'] ?? 0)); @endphp
        <span class="dtc-icon-count" data-sidebar-unread aria-label="{{ $count }} unread notifications" @if(!$count) hidden @endif>{{ $count > 99 ? '99+' : $count }}</span>
    @elseif(isset($item['work_key']))
        <span class="dtc-icon-count" data-work-count="{{ $item['work_key'] }}" data-work-description="{{ $item['work_description'] }}" aria-label="{{ $item['work_count'] }} {{ $item['work_description'] }}" @if(!$item['work_count']) hidden @endif>{{ $item['work_count'] > 99 ? '99+' : $item['work_count'] }}</span>
    @elseif(isset($item['label']))
        <span class="dtc-icon-count" @if(!$item['label']) hidden @endif>{{ is_numeric($item['label']) && $item['label'] > 99 ? '99+' : $item['label'] }}</span>
    @endif
</span>

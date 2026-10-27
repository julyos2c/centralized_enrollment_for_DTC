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
        @php $notificationCount = max(0, (int) ($item['label'] ?? 0)); @endphp
        <span class="dtc-sidebar-notification-count" data-sidebar-unread aria-label="{{ $notificationCount }} unread notifications" @if(!$notificationCount) hidden @endif>{{ $notificationCount > 99 ? '99+' : $notificationCount }}</span>
    @endif
</span>

<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item">

    <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="{{ $item['href'] }}" @isset($item['target']) target="{{ $item['target'] }}" @endisset
       {!! $item['data-compiled'] ?? '' !!}>

        @php
            $__dtcFaToLucide = include resource_path('views/vendor/adminlte/partials/sidebar/dtc-icon-map.php');
            $__dtcRawIcon    = $item['icon'] ?? 'circle';
            $__dtcStripped   = preg_replace('/\b(fa[srbl]?|fa-fw|fa-\d+x)\s*/i', '', $__dtcRawIcon);
            $__dtcBareName   = trim(preg_replace('/^fa-/i', '', trim($__dtcStripped)));
            $dtcLucideIcon   = $__dtcFaToLucide[$__dtcBareName] ?? ($__dtcBareName ?: 'circle');
        @endphp
        <i data-lucide="{{ $dtcLucideIcon }}"
           class="nav-icon {{ isset($item['icon_color']) ? 'text-'.$item['icon_color'] : '' }}"
           style="width:1.25em;height:1.25em;flex-shrink:0"></i>

        <p class="dtc-sidebar-menu-copy">
            <span class="dtc-sidebar-menu-label">{{ $item['text'] }}</span>
            @isset($item['work_key'])
                <span class="dtc-work-badge dtc-menu-badge" data-work-count="{{ $item['work_key'] }}" data-work-description="{{ $item['work_description'] }}" aria-label="{{ $item['work_count'] }} {{ $item['work_description'] }}" @if(!$item['work_count']) hidden @endif>{{ $item['work_count'] > 99 ? '99+' : $item['work_count'] }}</span>
            @endisset

            @isset($item['label'])
                <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} dtc-menu-badge">
                    {{ $item['label'] }}
                </span>
            @endisset
        </p>

    </a>

</li>

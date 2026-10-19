<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item has-treeview {{ $item['submenu_class'] }}">

    {{-- Menu toggler --}}
    <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="" {!! $item['data-compiled'] ?? '' !!}>

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

        <p>
            {{ $item['text'] }}
            <i data-lucide="chevron-left" class="right" style="width:1em;height:1em"></i>

            @isset($item['label'])
                <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} right">
                    {{ $item['label'] }}
                </span>
            @endisset
        </p>

    </a>

    {{-- Menu items --}}
    <ul class="nav nav-treeview">
        @each('adminlte::partials.sidebar.menu-item', $item['submenu'], 'item')
    </ul>

</li>

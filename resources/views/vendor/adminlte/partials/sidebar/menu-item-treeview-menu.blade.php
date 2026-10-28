<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item has-treeview {{ $item['submenu_class'] }}">

    {{-- Menu toggler --}}
    <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="#" role="button" aria-expanded="{{ str_contains($item['submenu_class'], 'menu-open') ? 'true' : 'false' }}" {!! $item['data-compiled'] ?? '' !!}>

        @include('adminlte::partials.sidebar.menu-item-icon')

        <p class="dtc-sidebar-menu-copy">
            <span class="dtc-sidebar-menu-label">{{ $item['text'] }}</span>
            <i data-lucide="chevron-left" class="right" style="width:1em;height:1em"></i>

        </p>

    </a>

    {{-- Menu items --}}
    <ul class="nav nav-treeview">
        @each('adminlte::partials.sidebar.menu-item', $item['submenu'], 'item')
    </ul>

</li>

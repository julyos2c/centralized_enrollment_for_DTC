<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item">

    <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="{{ $item['href'] }}" @isset($item['target']) target="{{ $item['target'] }}" @endisset
       {!! $item['data-compiled'] ?? '' !!}>

        @include('adminlte::partials.sidebar.menu-item-icon')

        <p class="dtc-sidebar-menu-copy">
            <span class="dtc-sidebar-menu-label">{{ $item['text'] }}</span>
        </p>

    </a>

</li>

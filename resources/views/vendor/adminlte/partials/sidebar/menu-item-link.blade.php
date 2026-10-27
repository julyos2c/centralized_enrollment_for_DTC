<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item">

    <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="{{ $item['href'] }}" @isset($item['target']) target="{{ $item['target'] }}" @endisset
       {!! $item['data-compiled'] ?? '' !!}>

        @include('adminlte::partials.sidebar.menu-item-icon')

        <p class="dtc-sidebar-menu-copy">
            <span class="dtc-sidebar-menu-label">{{ $item['text'] }}</span>
            @isset($item['work_key'])
                <span class="dtc-work-badge dtc-menu-badge" data-work-count="{{ $item['work_key'] }}" data-work-description="{{ $item['work_description'] }}" aria-label="{{ $item['work_count'] }} {{ $item['work_description'] }}" @if(!$item['work_count']) hidden @endif>{{ $item['work_count'] > 99 ? '99+' : $item['work_count'] }}</span>
            @endisset

            @if(isset($item['label']) && empty($item['is_notification']))
                <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} dtc-menu-badge">
                    {{ $item['label'] }}
                </span>
            @endisset
        </p>

    </a>

</li>

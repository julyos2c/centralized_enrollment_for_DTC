<aside class="main-sidebar {{ config('adminlte.classes_sidebar', 'sidebar-light-primary elevation-4') }}">

    {{-- Brand --}}
    @if(config('adminlte.logo_img_xl'))
        @include('adminlte::partials.common.brand-logo-xl')
    @else
        @include('adminlte::partials.common.brand-logo-xs')
    @endif

    {{-- Sidebar menu (AdminLTE manages this region's own scrolling via
         OverlayScrollbars at runtime — see notes in dtc-theme.css) --}}
    <div class="sidebar">
        <nav class="pt-1">
            <ul class="nav nav-pills nav-sidebar flex-column {{ config('adminlte.classes_sidebar_nav', '') }}"
                data-widget="treeview" role="menu"
                @if(config('adminlte.sidebar_nav_animation_speed') != 300)
                    data-animation-speed="{{ config('adminlte.sidebar_nav_animation_speed') }}"
                @endif
                @if(!config('adminlte.sidebar_nav_accordion'))
                    data-accordion="false"
                @endif>
                @each('adminlte::partials.sidebar.menu-item', $adminlte->menu('sidebar'), 'item')
            </ul>
        </nav>
    </div>

    {{-- Signed-in user panel: pinned to the bottom of the sidebar.
         Deliberately NOT nested inside .sidebar above — AdminLTE's JS
         applies its own scrollbar plugin to that element and would
         sweep this panel into the scrolling nav region with it. --}}
    @auth
        @php
            $sidebarRole = auth()->user()->getRoleNames()->first() ?? '';
            $sidebarRoleLabel = ucfirst(str_replace('_', ' ', $sidebarRole));
            $sidebarInitials = collect(preg_split('/\s+/', trim(auth()->user()->name)))
                ->filter()
                ->take(2)
                ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                ->implode('');
        @endphp
        <div class="dtc-sidebar-user-panel">
            <button class="dtc-sidebar-user-main"
                    type="button"
                    id="dtcSidebarProfileToggle"
                    aria-expanded="false"
                    aria-controls="dtcSidebarProfileMenu"
                    aria-label="Open account menu">
                <x-dtc.user-avatar :user="auth()->user()" class="dtc-sidebar-avatar" aria-hidden="true" />
                <div class="dtc-sidebar-user-copy">
                    <div class="dtc-sidebar-user-name" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</div>
                    <div class="dtc-sidebar-user-role">{{ $sidebarRoleLabel }}</div>
                </div>
                <span class="dtc-sidebar-profile-chevron" aria-hidden="true">
                    <i data-lucide="chevron-up"></i>
                </span>
            </button>
            <div class="dtc-sidebar-profile-menu" id="dtcSidebarProfileMenu" hidden>
                @if(auth()->user()->hasAnyRole(['student', 'alumni', 'new_applicant']))
                <a href="{{ route('portal.profile.show') }}" class="dtc-sidebar-profile-link {{ request()->routeIs('portal.profile.*') ? 'is-active' : '' }}">
                    <i data-lucide="user-cog"></i>
                    <span>Profile Settings</span>
                </a>
                @endif
                @if(auth()->user()->hasAnyRole(['admin', 'registrar', 'cashier']))
                <a href="{{ route('profile.show') }}" class="dtc-sidebar-profile-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                    <i data-lucide="user-cog"></i>
                    <span>Profile Settings</span>
                </a>
                @endif
                <a href="{{ route('notifications.index') }}" class="dtc-sidebar-profile-link">
                    <i data-lucide="bell"></i>
                    <span>Notifications</span>
                    @if(auth()->user()->unreadNotificationsCount() > 0)
                        <span class="dtc-sidebar-profile-badge">{{ auth()->user()->unreadNotificationsCount() }}</span>
                    @endif
                </a>
                @if(auth()->user()->hasRole('admin'))
                    <a href="{{ route('admin.settings.index') }}" class="dtc-sidebar-profile-link">
                        <i data-lucide="settings"></i>
                        <span>Settings</span>
                    </a>
                @endif
                <div class="dtc-sidebar-profile-divider"></div>
                <form id="logout-form" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="button"
                            class="dtc-sidebar-profile-link is-danger"
                            data-dtc-confirm
                            data-dtc-confirm-title="Log out?"
                            data-dtc-confirm-message="Are you sure you want to log out of your account?"
                            data-dtc-confirm-ok="Log Out"
                            data-dtc-confirm-tone="neutral"
                            data-dtc-confirm-form="#logout-form">
                        <i data-lucide="log-out"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    @endauth
</aside>

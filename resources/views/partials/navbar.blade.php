@section('content_top_nav_left')
@endsection


@section('content_top_nav_right')
@php
    $unread = \App\Models\UserNotification::where('user_id', auth()->id())
        ->whereNull('read_at')->count();
    $recentNotifs = \App\Models\UserNotification::where('user_id', auth()->id())
        ->latest()->take(5)->get();
@endphp
<li class="nav-item navbar-search-item">
    <div class="navbar-search-wrapper">

        {{-- Icon --}}
        <i data-lucide="search" class="navbar-search-icon"></i>

        {{-- Input --}}
        <input type="text"
               id="global-search"
               placeholder="Search anything..."
               class="navbar-search-input"
               autocomplete="off">

        {{-- Ctrl+K badge --}}
        <div class="search-kbd" id="search-kbd">
            <span class="search-kbd-key">Ctrl</span>
            <span class="search-kbd-key">K</span>
        </div>

        {{-- Clear button --}}
        <button class="search-clear-btn" id="search-clear" onclick="clearSearch()">
            <i data-lucide="x"></i>
        </button>

    </div>

    {{-- Dropdown --}}
    <div class="search-dropdown" id="search-dropdown">

        {{-- Default: Suggestions + Recent --}}
        <div id="search-default">
            <div class="search-section">
                <div class="search-section-label">Suggestions</div>
                <div id="suggestions-list"></div>
            </div>

            <div class="search-section-footer">
                <span class="search-section-label" style="margin:0;">Recent Searches</span>
                <button class="search-clear-recent" onclick="clearRecent()">Clear</button>
            </div>
            <div class="search-section" style="padding-top:10px;">
                <div class="search-recent-chips" id="recent-list"></div>
                <div class="search-no-recent u-hidden" id="no-recent" >
                    No recent searches yet.
                </div>
            </div>
        </div>

        {{-- Loading --}}
        <div class="search-loading u-hidden" id="search-loading" >
            <i data-lucide="loader" class="lucide-spin"></i>
        </div>

        {{-- Results --}}
        <div id="search-results"  class="u-hidden">
            <div class="search-section">
                <div class="search-section-label">Results</div>
                <div id="results-list"></div>
            </div>
        </div>

        {{-- Empty --}}
        <div class="search-empty u-hidden" id="search-empty" >
            <i data-lucide="search"></i>
            <div class="search-empty-title">No results found</div>
            <div class="search-empty-sub" id="empty-query"></div>
        </div>

    </div>
</li>

{{-- Dark Mode Toggle --}}
<li class="nav-item navbar-theme-toggle">
    <button
        type="button"
        id="darkModeToggle"
        class="theme-toggle-btn"
        aria-label="Enable dark mode"
        aria-pressed="false"
        title="Enable dark mode">
        <i data-lucide="moon" id="darkModeIcon" aria-hidden="true"></i>
    </button>
</li>

<!-- Notification Bell Dropdown -->
<li class="nav-item dropdown navbar-notification-item" id="notif-dropdown">
    <a href="#" class="nav-link dropdown-toggle navbar-icon-link"
       data-toggle="dropdown" id="notifBell" aria-label="Open notifications"
       aria-haspopup="true" aria-expanded="false">
        <i data-lucide="bell" class="navbar-icon" aria-hidden="true"></i>
        @if($unread > 0)
            <span id="notif-badge" class="navbar-notification-badge">
                {{ $unread }}
            </span>
        @endif
    </a>

    {{-- Dropdown Panel --}}
    <div class="dropdown-menu dropdown-menu-right p-0 notify-dropdown">

        {{-- Header --}}
        <div class="notify-header">
            <div>
                <div class="notify-header-title">
                    Notifications
                </div>
                <div class="notify-header-subtitle">
                    @if($unread > 0)
                        {{ $unread }} unread notification{{ $unread > 1 ? 's' : '' }}
                    @else
                        All caught up!
                    @endif
                </div>
            </div>
            @if($unread > 0)
            <a href="{{ route('notifications.index') }}" class="notify-mark-read">
                Mark all read
            </a>
            @endif
        </div>

        {{-- Notification List --}}
        <div class="notify-list">
            @forelse($recentNotifs as $notif)
            <a href="{{ route('notifications.read', $notif) }}"
               class="notif-item {{ $notif->isRead() ? 'is-read' : 'is-unread' }}">

                {{-- Icon --}}
                <div class="notif-icon notif-icon-{{ $notif->type }}">
                    <i data-lucide="{{ $notif->type === 'success' ? 'check-circle' :
                                    ($notif->type === 'danger'   ? 'x-circle' :
                                    ($notif->type === 'warning'  ? 'alert-circle' : 'info')) }}"></i>
                </div>

                {{-- Content --}}
                <div class="notif-body">
                    <div class="notif-row">
                        <div class="notif-title {{ $notif->isRead() ? 'is-read' : 'is-unread' }}">
                            {{ $notif->title }}
                        </div>
                        @if(!$notif->isRead())
                            <div class="notif-dot" aria-label="Unread notification"></div>
                        @endif
                    </div>
                    <div class="notif-message">
                        {{ Str::limit($notif->message, 60) }}
                    </div>
                    <div class="notif-time">
                        <i data-lucide="clock" aria-hidden="true"></i>
                        {{ $notif->created_at->diffForHumans() }}
                    </div>
                </div>
            </a>
            @empty
            <div class="notify-empty">
                <i data-lucide="bell-off" class="notify-empty-icon" aria-hidden="true"></i>
                <div class="notify-empty-title">
                    No notifications yet
                </div>
                <div class="notify-empty-subtitle">
                    You're all caught up!
                </div>
            </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if($recentNotifs->isNotEmpty())
        <div class="notify-footer">
            <a href="{{ route('notifications.index') }}" class="notify-view-all">
                View all notifications →
            </a>
        </div>
        @endif
    </div>
</li>

@endsection
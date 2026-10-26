@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Notifications')
@section('content_header')
<div class="notice-heading">
    <div><h4 class="mb-1 font-weight-bold u-text">Notifications</h4><p class="u-text-secondary-sm mb-0">Updates and next steps, all in one place.</p></div>
    <div class="notice-heading-actions">
        <button type="button" class="notice-button" data-notice-all data-url="{{ route('notifications.markAllRead') }}" @disabled(!$unreadCount)><i data-lucide="check-check" aria-hidden="true"></i>Mark all as read</button>
        <button type="button" class="notice-button notice-button--muted" data-notice-delete-read data-url="{{ route('notifications.destroyRead') }}" @disabled(!$readCount)><i data-lucide="trash-2" aria-hidden="true"></i>Delete read</button>
    </div>
</div>
@endsection
@section('content')
<section class="notice-page" aria-label="Your notifications">
    <div class="notice-new-updates" id="notice-new-updates" hidden role="status">
        <span>New notifications are available.</span>
        <a href="{{ route('notifications.index', request()->only(['search', 'state', 'category', 'per_page'])) }}">Show new updates</a>
    </div>
    @if(session('info'))<div class="alert alert-info" role="status">{{ session('info') }}</div>@endif
    <form method="GET" action="{{ route('notifications.index') }}" class="dtc-fl notice-filter-form">
        <input type="hidden" name="state" value="{{ $state }}">
        <div class="dtc-fl-toolbar">
            <div class="notice-search"><i data-lucide="search" aria-hidden="true"></i><input type="search" name="search" value="{{ request('search') }}" maxlength="200" placeholder="Search notifications" aria-label="Search notifications"></div>
            <button type="button" class="dtc-fl-toggle" data-toggle="collapse" data-target="#noticeFilters" aria-controls="noticeFilters" aria-expanded="{{ request('category') ? 'true' : 'false' }}"><i data-lucide="sliders-horizontal" aria-hidden="true"></i>Filters</button>
            <button type="submit" class="sr-only">Search notifications</button>
        </div>
        <div id="noticeFilters" class="collapse {{ request('category') ? 'show' : '' }}">
            <div class="dtc-fl-panel">
                <div class="dtc-fl-field"><label for="notice-category">Notification type</label><select id="notice-category" name="category" class="form-control"><option value="">All types</option>@foreach(\App\Models\UserNotification::CATEGORIES as $key => $category)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $category['label'] }}</option>@endforeach</select></div>
                <div class="dtc-fl-field"><label for="notice-limit">Rows per page</label><select id="notice-limit" name="per_page" class="form-control">@foreach(\App\Support\TablePagination::OPTIONS as $limit)<option value="{{ $limit }}" @selected($notifications->perPage() === $limit)>{{ $limit }}</option>@endforeach</select></div>
                <div class="dtc-fl-actions"><button type="submit" class="btn btn-primary">Apply</button><a href="{{ route('notifications.index') }}" class="dtc-fl-clear">Reset</a></div>
            </div>
        </div>
    </form>
    <div class="notice-panel">
        <nav class="notice-tabs" aria-label="Notification read status">
            @foreach(['all' => ['All', $totalCount], 'unread' => ['Unread', $unreadCount], 'read' => ['Read', $readCount]] as $key => $tab)
                <a href="{{ route('notifications.index', array_merge(request()->only(['search', 'category', 'per_page']), ['state' => $key])) }}" class="notice-tab {{ $state === $key ? 'is-active' : '' }}" aria-current="{{ $state === $key ? 'page' : 'false' }}">{{ $tab[0] }}<span data-notice-count="{{ $key }}">{{ $tab[1] }}</span></a>
            @endforeach
        </nav>
        @if($notifications->isEmpty())
            <div class="notice-empty"><i data-lucide="{{ $totalCount ? 'search' : 'bell-off' }}" aria-hidden="true"></i><h5>{{ $totalCount ? 'No matching notifications' : 'You?re all caught up' }}</h5><p>{{ $totalCount ? 'Try another search or change your filters.' : 'New updates will appear here.' }}</p>@if($totalCount)<a href="{{ route('notifications.index') }}">View all notifications</a>@endif</div>
        @else
            <ul class="notice-list">
                @foreach($notifications as $notification)
                    @php
                        $category = \App\Models\UserNotification::CATEGORIES[$notification->category];
                        $tone = in_array($notification->type, ['success', 'danger', 'warning'], true) ? $notification->type : 'info';
                    @endphp
                    <li class="notice-card {{ $notification->isRead() ? 'is-read' : 'is-unread' }}" data-notice-id="{{ $notification->id }}" data-read="{{ $notification->isRead() ? '1' : '0' }}">
                        <span class="notice-icon is-{{ $tone }}"><i data-lucide="{{ $category['icon'] }}" aria-hidden="true"></i></span>
                        <div class="notice-content">
                            <div class="notice-title-row"><h2>{{ $notification->title }}</h2><span class="notice-unread-dot" @if($notification->isRead()) hidden @endif><span class="sr-only">Unread</span></span></div>
                            <p>{{ $notification->message }}</p>
                            <div class="notice-meta"><span>{{ $category['label'] }}</span><time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('M j, Y, g:i A') }}">{{ $notification->created_at->diffForHumans() }}</time></div>
                            <div class="notice-actions">
                                @if($notification->link)<a class="notice-open" href="{{ route('notifications.open', $notification) }}">View update<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>@endif
                                <button type="button" class="notice-state" data-notice-state data-url="{{ route('notifications.state', $notification) }}" data-read="{{ $notification->isRead() ? '1' : '0' }}">{{ $notification->isRead() ? 'Mark unread' : 'Mark as read' }}</button>
                                <button type="button" class="notice-delete" data-notice-delete data-url="{{ route('notifications.destroy', $notification) }}" aria-label="Delete notification: {{ $notification->title }}"><i data-lucide="trash-2" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <footer class="notice-pagination">
                <span>{{ $notifications->firstItem() }}&ndash;{{ $notifications->lastItem() }} of {{ $notifications->total() }} updates</span>
                <div class="notice-desktop-pages">{{ $notifications->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
                @if($notifications->hasPages())
                <nav class="notice-mobile-pages" aria-label="Notification pages">
                    @if($notifications->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $notifications->previousPageUrl() }}" rel="prev">Previous</a>@endif
                    <span class="notice-page-number">{{ $notifications->currentPage() }} / {{ $notifications->lastPage() }}</span>
                    @if($notifications->hasMorePages())<a href="{{ $notifications->nextPageUrl() }}" rel="next">Next</a>@else<span aria-disabled="true">Next</span>@endif
                </nav>
                @endif
            </footer>
        @endif
    </div>
</section>
@endsection
@push('css')<link rel="stylesheet" href="{{ asset_v('css/dtc-filters.css') }}">@endpush

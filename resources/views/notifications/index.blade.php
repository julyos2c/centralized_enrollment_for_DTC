@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Notifications')

@section('content_header')
    <div class="dtc-notif-page-header">
        <div class="dtc-notif-page-header-left">
            <h4 class="mb-0 font-weight-bold u-text">Notifications</h4>
            <p class="mb-0 u-text-secondary-sm">Your system alerts and updates</p>
        </div>
        <div class="dtc-notif-page-header-right">
            @if($notifications->isNotEmpty())
                @if($unreadCount > 0)
                <button id="btn-mark-all-read" class="btn btn-sm btn-outline-primary">
                    <i data-lucide="check-check" style="width:13px;height:13px;margin-right:4px;"></i>
                    Mark all read
                </button>
                @endif
                <button id="btn-delete-read" class="btn btn-sm btn-outline-danger"
                        {{ $notifications->filter(fn($n) => $n->isRead())->isEmpty() ? 'disabled' : '' }}>
                    <i data-lucide="trash-2" style="width:13px;height:13px;margin-right:4px;"></i>
                    <span class="dtc-notif-btn-label">Delete read</span>
                </button>
            @endif
        </div>
    </div>
@endsection

@section('content')

@if ($notifications->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-lucide="bell-off" class="mb-3" style="width:52px;height:52px;color:var(--dtc-text-muted);display:block;margin:0 auto 12px;"></i>
            <h5 style="color:var(--dtc-text);font-weight:700;">You're all caught up</h5>
            <p class="u-text-secondary-sm mb-0">No notifications at the moment.</p>
        </div>
    </div>
@else

{{-- Tabs --}}
<div class="dtc-notif-tabs mb-3">
    <button class="dtc-notif-tab active" data-tab="all">
        All
        <span class="dtc-notif-tab-count">{{ $notifications->count() }}</span>
    </button>
    <button class="dtc-notif-tab" data-tab="unread">
        Unread
        @if($unreadCount > 0)
        <span class="dtc-notif-tab-count is-danger">{{ $unreadCount }}</span>
        @endif
    </button>
    <button class="dtc-notif-tab" data-tab="read">
        Read
        <span class="dtc-notif-tab-count">{{ $notifications->filter(fn($n) => $n->isRead())->count() }}</span>
    </button>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8 dtc-notif-col">

        @php
            $grouped = $notifications->groupBy(function ($n) {
                if ($n->created_at->isToday())     return 'Today';
                if ($n->created_at->isYesterday()) return 'Yesterday';
                return 'Earlier';
            });
            $groupOrder = ['Today', 'Yesterday', 'Earlier'];
        @endphp

        @foreach ($groupOrder as $group)
            @if(isset($grouped[$group]))
            <div class="dtc-notif-group" data-group="{{ $group }}">
                <div class="dtc-notif-group-label">{{ $group }}</div>

                @foreach ($grouped[$group] as $notification)
                @php
                    $tone = match($notification->type) {
                        'success' => 'success',
                        'danger'  => 'danger',
                        'warning' => 'warning',
                        default   => 'info',
                    };
                    $icon = match($notification->type) {
                        'success' => 'check-circle',
                        'danger'  => 'x-circle',
                        'warning' => 'alert-circle',
                        default   => 'info',
                    };
                    $borderColor = match($notification->type) {
                        'success' => 'var(--dtc-success)',
                        'danger'  => 'var(--dtc-danger)',
                        'warning' => 'var(--dtc-accent)',
                        default   => 'var(--dtc-primary)',
                    };
                @endphp

                <div class="dtc-notif-item {{ $notification->isRead() ? 'is-read' : 'is-unread' }}"
                     data-id="{{ $notification->id }}"
                     data-read="{{ $notification->isRead() ? '1' : '0' }}"
                     style="border-left:3px solid {{ $borderColor }};">

                    {{-- Unread dot --}}
                    @if(!$notification->isRead())
                    <div class="dtc-notif-unread-dot"></div>
                    @endif

                    {{-- Icon --}}
                    <div class="dtc-icon-swatch is-{{ $tone }}" style="width:40px;height:40px;border-radius:12px;flex-shrink:0;">
                        <i data-lucide="{{ $icon }}"></i>
                    </div>

                    {{-- Content --}}
                    <div class="dtc-notif-content">
                        <div class="dtc-notif-title-row">
                            <span class="dtc-notif-title">{{ $notification->title }}</span>
                            @if(!$notification->isRead())
                            <span class="dtc-status-badge is-danger" style="font-size:9px;font-weight:700;padding:2px 7px;">NEW</span>
                            @endif
                        </div>
                        <p class="dtc-notif-message">{{ $notification->message }}</p>
                        <div class="dtc-notif-meta">
                            <i data-lucide="clock" style="width:11px;height:11px;margin-right:3px;"></i>
                            {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </div>

                    {{-- Actions: delete on top, view on bottom --}}
                    <div class="dtc-notif-actions">
                        <button class="dtc-notif-btn-delete" title="Delete"
                                data-id="{{ $notification->id }}"
                                data-url="{{ route('notifications.destroy', $notification) }}">
                            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                        </button>
                        @if($notification->link)
                        <a href="{{ route('notifications.read', $notification) }}"
                           class="dtc-notif-btn-view" title="View">
                            <i data-lucide="arrow-right" style="width:13px;height:13px;"></i>
                            <span class="dtc-notif-btn-view-text">View</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        @endforeach

        {{-- Per-tab empty states (shown by JS) --}}
        <div id="dtc-notif-empty-unread" class="dtc-notif-empty" style="display:none;">
            <i data-lucide="check-circle" style="width:40px;height:40px;color:var(--dtc-success);display:block;margin:0 auto 10px;"></i>
            <p class="mb-0" style="font-weight:600;color:var(--dtc-text);">All caught up!</p>
            <p class="u-text-secondary-sm mb-0">No unread notifications.</p>
        </div>
        <div id="dtc-notif-empty-read" class="dtc-notif-empty" style="display:none;">
            <i data-lucide="inbox" style="width:40px;height:40px;color:var(--dtc-text-muted);display:block;margin:0 auto 10px;"></i>
            <p class="mb-0" style="font-weight:600;color:var(--dtc-text);">No read notifications.</p>
        </div>

    </div>
</div>
@endif

@endsection

@section('js')
<script>
(function () {

    var MARK_ALL_URL    = '{{ route("notifications.markAllRead") }}';
    var DELETE_READ_URL = '{{ route("notifications.destroyRead") }}';
    var CSRF            = '{{ csrf_token() }}';

    // -- Tabs ------------------------------------------------------------------
    var tabs   = document.querySelectorAll('.dtc-notif-tab');
    var items  = document.querySelectorAll('.dtc-notif-item');
    var groups = document.querySelectorAll('.dtc-notif-group');

    function applyTab(tab) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === tab); });

        var visibleCount = 0;
        items.forEach(function (item) {
            var show = tab === 'all'
                     || (tab === 'unread' && item.dataset.read === '0')
                     || (tab === 'read'   && item.dataset.read === '1');
            item.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        groups.forEach(function (g) {
            var visible = Array.from(g.querySelectorAll('.dtc-notif-item'))
                              .some(function (i) { return i.style.display !== 'none'; });
            g.style.display = visible ? '' : 'none';
        });

        var emptyUnread = document.getElementById('dtc-notif-empty-unread');
        var emptyRead   = document.getElementById('dtc-notif-empty-read');
        if (emptyUnread) emptyUnread.style.display = (tab === 'unread' && visibleCount === 0) ? 'block' : 'none';
        if (emptyRead)   emptyRead.style.display   = (tab === 'read'   && visibleCount === 0) ? 'block' : 'none';
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { applyTab(t.dataset.tab); });
    });

    // -- Mark all read ---------------------------------------------------------
    var btnMarkAll = document.getElementById('btn-mark-all-read');
    if (btnMarkAll) {
        btnMarkAll.addEventListener('click', function () {
            fetch(MARK_ALL_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).then(function () {
                items.forEach(function (item) {
                    item.classList.remove('is-unread');
                    item.classList.add('is-read');
                    item.dataset.read = '1';
                    var dot   = item.querySelector('.dtc-notif-unread-dot');
                    var badge = item.querySelector('.dtc-status-badge.is-danger');
                    if (dot)   dot.remove();
                    if (badge) badge.remove();
                });
                btnMarkAll.style.display = 'none';
                var activeTab = document.querySelector('.dtc-notif-tab.active');
                if (activeTab) applyTab(activeTab.dataset.tab);
                var btnDel = document.getElementById('btn-delete-read');
                if (btnDel) btnDel.disabled = false;
                if (window.dtcToast) dtcToast.success('All notifications marked as read.');
            });
        });
    }

    // -- Delete individual - with dtcConfirmModal ------------------------------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.dtc-notif-btn-delete');
        if (!btn) return;

        var item = btn.closest('.dtc-notif-item');
        var url  = btn.dataset.url;

        function doDelete() {
            fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).then(function (r) {
                if (!r.ok) return;
                item.style.transition = 'opacity 0.2s, transform 0.2s';
                item.style.opacity    = '0';
                item.style.transform  = 'translateX(20px)';
                setTimeout(function () {
                    var group = item.closest('.dtc-notif-group');
                    item.remove();
                    if (group && !group.querySelector('.dtc-notif-item')) group.remove();
                    var activeTab = document.querySelector('.dtc-notif-tab.active');
                    if (activeTab) applyTab(activeTab.dataset.tab);
                }, 200);
            });
        }

        // Use dtcConfirmModal if available, otherwise native confirm
        var modal  = document.getElementById('dtcConfirmModal');
        if (modal && window.jQuery) {
            modal.className = 'modal fade dtc-confirm-modal is-danger';
            var titleEl = modal.querySelector('#dtcConfirmTitle');
            var msgEl   = modal.querySelector('#dtcConfirmMessage');
            var okBtn   = modal.querySelector('#dtcConfirmOk');
            if (titleEl) titleEl.textContent = 'Delete Notification?';
            if (msgEl)   msgEl.textContent   = 'This notification will be permanently removed.';
            var newOk = okBtn.cloneNode(true);
            okBtn.parentNode.replaceChild(newOk, okBtn);
            newOk.textContent = 'Delete';
            newOk.classList.add('dtc-btn-danger');
            newOk.addEventListener('click', function () {
                jQuery('#dtcConfirmModal').modal('hide');
                doDelete();
            });
            jQuery('#dtcConfirmModal').modal('show');
        } else {
            if (window.confirm('Delete this notification?')) doDelete();
        }
    });

    // -- Delete all read - with dtcConfirmModal --------------------------------
    var btnDeleteRead = document.getElementById('btn-delete-read');
    if (btnDeleteRead) {
        btnDeleteRead.addEventListener('click', function () {
            var modal  = document.getElementById('dtcConfirmModal');
            if (modal && window.jQuery) {
                modal.className = 'modal fade dtc-confirm-modal is-danger';
                var titleEl = modal.querySelector('#dtcConfirmTitle');
                var msgEl   = modal.querySelector('#dtcConfirmMessage');
                var okBtn   = modal.querySelector('#dtcConfirmOk');
                if (titleEl) titleEl.textContent = 'Delete Read Notifications?';
                if (msgEl)   msgEl.textContent   = 'All read notifications will be permanently removed.';
                var newOk = okBtn.cloneNode(true);
                okBtn.parentNode.replaceChild(newOk, okBtn);
                newOk.textContent = 'Delete All Read';
                newOk.classList.add('dtc-btn-danger');
                newOk.addEventListener('click', function () {
                    jQuery('#dtcConfirmModal').modal('hide');
                    fetch(DELETE_READ_URL, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                    }).then(function () {
                        document.querySelectorAll('.dtc-notif-item.is-read').forEach(function (item) {
                            var group = item.closest('.dtc-notif-group');
                            item.remove();
                            if (group && !group.querySelector('.dtc-notif-item')) group.remove();
                        });
                        if (btnDeleteRead) btnDeleteRead.disabled = true;
                        var activeTab = document.querySelector('.dtc-notif-tab.active');
                        if (activeTab) applyTab(activeTab.dataset.tab);
                        if (window.dtcToast) dtcToast.success('All read notifications deleted.');
                    });
                });
                jQuery('#dtcConfirmModal').modal('show');
            } else {
                if (window.confirm('Delete all read notifications? This cannot be undone.')) {
                    fetch(DELETE_READ_URL, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                    }).then(function () {
                        document.querySelectorAll('.dtc-notif-item.is-read').forEach(function (item) {
                            var group = item.closest('.dtc-notif-group');
                            item.remove();
                            if (group && !group.querySelector('.dtc-notif-item')) group.remove();
                        });
                        if (btnDeleteRead) btnDeleteRead.disabled = true;
                        var activeTab = document.querySelector('.dtc-notif-tab.active');
                        if (activeTab) applyTab(activeTab.dataset.tab);
                        if (window.dtcToast) dtcToast.success('All read notifications deleted.');
                    });
                }
            }
        });
    }

})();
</script>
@endsection

<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'state' => ['nullable', 'in:all,unread,read'],
            'category' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(UserNotification::CATEGORIES))],
        ]);
        $state = $filters['state'] ?? 'all';
        $base = UserNotification::where('user_id', Auth::id());
        $totalCount = (clone $base)->count();
        $unreadCount = (clone $base)->whereNull('read_at')->count();
        $readCount = $totalCount - $unreadCount;
        if ($state === 'unread') $base->whereNull('read_at');
        if ($state === 'read') $base->whereNotNull('read_at');
        if (!empty($filters['category'])) $base->inCategory($filters['category']);
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $base->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('message', 'like', '%'.$search.'%'));
        }
        $notifications = $base->latest()->orderByDesc('id')->paginate(\App\Support\TablePagination::perPage(10))->withQueryString();
        if ($notifications->currentPage() > $notifications->lastPage()) {
            return redirect($request->fullUrlWithQuery(['page' => $notifications->lastPage()]));
        }
        return view('notifications.index', compact('notifications', 'unreadCount', 'readCount', 'totalCount', 'state'));
    }

    /** Return only this Cashier's notification deltas for navbar polling. */
    public function cashierPoll(Request $request)
    {
        abort_unless($request->user()->hasRole('cashier'), 403);
        return $this->poll($request);
    }

    /** Notification polling, always scoped to the authenticated recipient. */
    public function poll(Request $request)
    {
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'welcome_token' => ['nullable', 'uuid'],
        ]);
        $query = UserNotification::where('user_id', $request->user()->id)
            ->where('id', '>', (int) ($data['after_id'] ?? 0))
            ->orderBy('id');
        $batch = $query->limit(51)->get();
        $hasMore = $batch->count() > 50;
        $serialize = function (UserNotification $notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => $notification->isRead(),
                'relative_time' => $notification->created_at->diffForHumans(),
                'read_url' => route('notifications.read', $notification),
                'open_url' => route('notifications.open', $notification),
                'state_url' => route('notifications.state', $notification),
                'category_icon' => UserNotification::CATEGORIES[$notification->category]['icon'],
            ];
        };
        $notifications = $batch->take(50)->map($serialize)->values();
        $recentNotifications = UserNotification::where('user_id', $request->user()->id)
            ->latest()->orderByDesc('id')->limit(5)->get()->map($serialize)->values();

        $totalCount = UserNotification::where('user_id', $request->user()->id)->count();
        $unreadCount = $request->user()->unreadNotificationsCount();
        return response()->json([
            'notifications' => $notifications,
            'recent_notifications' => $recentNotifications,
            'work_counts' => app(\App\Services\SidebarWorkCounts::class)->forUser($request->user()),
            'student_welcome' => \App\Services\StudentWelcome::pending($request->user(), $data['welcome_token'] ?? null),
            'unread_count' => $unreadCount,
            'total_count' => $totalCount,
            'read_count' => $totalCount - $unreadCount,
            'has_more' => $hasMore,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function studentWelcome(Request $request)
    {
        $data = $request->validate(['action' => ['required', 'in:claim,acknowledge,release'], 'token' => ['required', 'uuid']]);
        $accepted = \App\Services\StudentWelcome::update($request->user(), $data['action'], $data['token']);
        return response()->json(['accepted' => $accepted])->header('Cache-Control', 'no-store, private');
    }

    /** Mark one notification as read and redirect to its link. */
    public function markRead(UserNotification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->markAsRead();

        return redirect($this->safeDestination($notification->link));
    }

    /** Opening a notification does not implicitly change its read state. */
    public function open(UserNotification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);
        $destination = $this->safeDestination($notification->link);
        $response = redirect($destination);
        if ($destination === route('notifications.index')) {
            $response->with('info', 'This update has no available destination. Its details remain in your notifications.');
        }
        return $response;
    }

    public function updateState(Request $request, UserNotification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);
        $data = $request->validate(['read' => ['required', 'boolean']]);
        $notification->update(['read_at' => $data['read'] ? ($notification->read_at ?? now()) : null]);
        return response()->json(['success' => true, 'read' => $notification->isRead(), 'unread_count' => $request->user()->unreadNotificationsCount()]);
    }

    /** Mark all unread notifications as read (AJAX or redirect). */
    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotificationsCount()]);
        }

        return back();
    }

    /** Delete a single notification. */
    public function destroy(Request $request, UserNotification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotificationsCount()]);
        }

        return back();
    }

    /** Delete all read notifications for the current user. */
    public function destroyRead(Request $request)
    {
        UserNotification::where('user_id', Auth::id())
            ->whereNotNull('read_at')
            ->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotificationsCount()]);
        }

        return back();
    }

    private function safeDestination(?string $link): string
    {
        if (! is_string($link) || $link === '') {
            return route('notifications.index');
        }

        $parts = parse_url($link);
        if ($parts === false || isset($parts['user'], $parts['pass'])) {
            return route('notifications.index');
        }

        if (isset($parts['host'])) {
            $applicationHost = parse_url(config('app.url'), PHP_URL_HOST);
            $requestHost = request()->getHost();
            $allowedHosts = array_filter([$applicationHost, $requestHost], 'is_string');

            if (! collect($allowedHosts)->contains(fn ($host) => hash_equals(strtolower($host), strtolower($parts['host'])))) {
                return route('notifications.index');
            }
        } elseif (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return route('notifications.index');
        }

        // Redirect through the current application host so notifications created
        // behind a proxy or under an old APP_URL still open their intended page.
        $path = $parts['path'] ?? '/';
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return route('notifications.index');
        }
        $destination = $path
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        if (preg_match('#^/portal/enrollment/(\d+)/payment-info/?$#', $path, $paymentMatch)) {
            if (!Auth::user()->hasAnyRole(['student', 'alumni', 'new_applicant'])) return route('notifications.index');
            $owned = Enrollment::whereKey($paymentMatch[1])->where('user_id', Auth::id())->exists();
            return $owned
                ? route('portal.enrollment.payment-info', (int) $paymentMatch[1])
                : route('notifications.index');
        }

        // Historical notifications stored the standalone enrollment receipt URL.
        // Open the current receipt modal on the payment list instead.
        if (preg_match('#^/cashier/payments/(\d+)/receipt/?$#', $path, $receiptMatch)) {
            if (!Auth::user()->hasAnyRole(['cashier', 'admin'])) return route('notifications.index');
            $available = \App\Models\Payment::where('enrollment_id', $receiptMatch[1])
                ->where('status', 'verified')->whereHas('enrollment')->exists();
            return $available
                ? route('cashier.payments.index', ['receipt' => (int) $receiptMatch[1]])
                : route('cashier.payments.index');
        }

        if (
            Auth::user()?->hasAnyRole(['registrar', 'admin'])
            && preg_match('#^/registrar/enrollments/(\d+)$#', $path, $matches)
        ) {
            $enrollment = Enrollment::whereKey($matches[1])->where('status', 'pending')->first();

            return $enrollment
                ? route('registrar.enrollments.index', ['review' => $enrollment->id])
                : route('registrar.enrollments.index');
        }

        try {
            // Resolve only GET routes; never follow obsolete or write-only links.
            $target = app('router')->getRoutes()->match(Request::create(url($destination), 'GET'));
            if ($target->getName() === 'notifications.read' || $target->getName() === 'notifications.open') {
                return route('notifications.index');
            }
            foreach ($target->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                    $roles = explode('|', substr($middleware, 5));
                    if (!Auth::user()->hasAnyRole($roles)) return route('notifications.index');
                }
            }
            app('router')->substituteBindings($target);
            app('router')->substituteImplicitBindings($target);
            // Portal record ownership must also match; destination controllers retain
            // their own authorization checks as the final authority.
            if (str_starts_with($path, '/portal/')) {
                foreach ($target->parameters() as $record) {
                    if ($record instanceof \Illuminate\Database\Eloquent\Model && $record->getAttribute('user_id') !== null
                        && (int) $record->getAttribute('user_id') !== (int) Auth::id()) {
                        return route('notifications.index');
                    }
                }
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface | \Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return route('notifications.index');
        }
        return url($destination);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = UserNotification::where('user_id', Auth::id())
            ->latest()
            ->get();

        $unreadCount = $notifications->filter(fn($n) => !$n->isRead())->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /** Return only this Cashier's notification deltas for navbar polling. */
    public function cashierPoll(Request $request)
    {
        abort_unless($request->user()->hasRole('cashier'), 403);
        return $this->poll($request);
    }

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
        $notifications = $batch->take(50)->map(function (UserNotification $notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => $notification->isRead(),
                'relative_time' => $notification->created_at->diffForHumans(),
                'read_url' => route('notifications.read', $notification),
            ];
        })->values();

        return response()->json([
            'notifications' => $notifications,
            'student_welcome' => \App\Services\StudentWelcome::pending($request->user(), $data['welcome_token'] ?? null),
            'unread_count' => $request->user()->unreadNotificationsCount(),
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

    /** Mark all unread notifications as read (AJAX or redirect). */
    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /** Delete a single notification. */
    public function destroy(Request $request, UserNotification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
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
            return response()->json(['success' => true]);
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

        if (
            Auth::user()?->hasAnyRole(['registrar', 'admin'])
            && preg_match('#^/registrar/enrollments/(\d+)$#', $path, $matches)
        ) {
            $enrollment = Enrollment::whereKey($matches[1])->where('status', 'pending')->first();

            return $enrollment
                ? route('registrar.enrollments.index', ['review' => $enrollment->id])
                : route('registrar.enrollments.index');
        }

        return url($destination);
    }
}

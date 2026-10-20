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

        if (! isset($parts['host'])) {
            return str_starts_with($link, '/') ? $link : route('notifications.index');
        }

        $applicationHost = parse_url(config('app.url'), PHP_URL_HOST);
        if (! is_string($applicationHost) || ! hash_equals(strtolower($applicationHost), strtolower($parts['host']))) {
            return route('notifications.index');
        }

        $path = $parts['path'] ?? '';
        if (
            Auth::user()?->hasAnyRole(['registrar', 'admin'])
            && preg_match('#^/registrar/enrollments/(\d+)$#', $path, $matches)
        ) {
            $enrollment = Enrollment::whereKey($matches[1])->where('status', 'pending')->first();

            return $enrollment
                ? route('registrar.enrollments.index', ['review' => $enrollment->id])
                : route('registrar.enrollments.index');
        }

        return $link;
    }
}

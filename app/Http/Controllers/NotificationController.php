<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
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

        return redirect($notification->link ?? route('notifications.index'));
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
}
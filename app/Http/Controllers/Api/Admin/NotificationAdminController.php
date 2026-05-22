<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationAdminController extends Controller
{
    /**
     * List the authenticated admin's database notifications (most recent first),
     * along with the unread count for the bell badge.
     */
    public function index(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'filter' => 'nullable|in:all,read,unread',
        ]);

        $user = Auth::user();
        $limit = (int) $request->input('limit', 15);
        $filter = $request->input('filter', 'all');

        $query = $user->notifications();
        if ($filter === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $notifications->map(fn ($n) => $this->transform($n))->all(),
            'meta' => [
                'unread_count' => $user->unreadNotifications()->count(),
                'total' => $user->notifications()->count(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->whereKey($id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $this->transform($notification->fresh()),
            'meta' => ['unread_count' => $user->unreadNotifications()->count()],
        ]);
    }

    /**
     * Mark all of the admin's notifications as read.
     */
    public function markAllRead(Request $request)
    {
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'meta' => ['unread_count' => 0],
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->whereKey($id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'meta' => ['unread_count' => $user->unreadNotifications()->count()],
        ]);
    }

    private function transform($notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? '',
            'icon' => $data['icon'] ?? 'heroicon-o-bell',
            'color' => $data['color'] ?? 'primary',
            'url' => $data['url'] ?? null,
            'is_read' => !is_null($notification->read_at),
            'time_ago' => $notification->created_at->diffForHumans(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}

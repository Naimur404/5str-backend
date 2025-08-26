<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    /**
     * Get user's notification list with pagination and filtering
     * Supports read/unread filtering, pagination, and search
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'filter' => 'nullable|in:all,read,unread',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|in:created_at,read_at,type',
                'sort_order' => 'nullable|in:asc,desc'
            ]);

            $user = Auth::user();
            $perPage = $request->input('per_page', 20);
            $filter = $request->input('filter', 'all');
            $search = $request->input('search');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Build query for user's notifications
            $query = $user->notifications();

            // Apply read/unread filter
            switch ($filter) {
                case 'read':
                    $query->whereNotNull('read_at');
                    break;
                case 'unread':
                    $query->whereNull('read_at');
                    break;
                // 'all' - no filter applied
            }

            // Apply search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereRaw("JSON_EXTRACT(data, '$.title') LIKE ?", ["%{$search}%"])
                      ->orWhereRaw("JSON_EXTRACT(data, '$.body') LIKE ?", ["%{$search}%"]);
                });
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Get paginated results
            $notifications = $query->paginate($perPage);

            // Transform notification data
            $transformedNotifications = $notifications->map(function($notification) {
                return [
                    'id' => $notification->id,
                    // 'type' => $notification->type,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'body' => $notification->data['body'] ?? '',
                    'icon' => $notification->data['icon'] ?? 'heroicon-o-bell',
                    'color' => $notification->data['color'] ?? 'primary',
                    // 'url' => $notification->data['url'] ?? null,
                    // 'actions' => $notification->data['actions'] ?? null,
                    'is_read' => !is_null($notification->read_at),
                    'read_at' => $notification->read_at,
                    // 'created_at' => $notification->created_at,
                    'time_ago' => $notification->created_at->diffForHumans(),
                ];
            });

            // Get notification statistics
            $stats = [
                'total_count' => $user->notifications()->count(),
                'unread_count' => $user->unreadNotifications()->count(),
                'read_count' => $user->notifications()->whereNotNull('read_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $transformedNotifications,
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                        'has_more' => $notifications->hasMorePages(),
                        'from' => $notifications->firstItem(),
                        'to' => $notifications->lastItem(),
                    ],
                    'stats' => $stats,
                    'filters' => [
                        'current_filter' => $filter,
                        'search' => $search,
                        'sort_by' => $sortBy,
                        'sort_order' => $sortOrder,
                    ]
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Notification list error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get notification details by ID
     */
    public function show(Request $request, $notificationId)
    {
        try {
            $user = Auth::user();
            
            $notification = $user->notifications()->where('id', $notificationId)->first();
            
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            // Transform notification data
            $transformedNotification = [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->data['title'] ?? 'Notification',
                'body' => $notification->data['body'] ?? '',
                'icon' => $notification->data['icon'] ?? 'heroicon-o-bell',
                'color' => $notification->data['color'] ?? 'primary',
                'url' => $notification->data['url'] ?? null,
                'actions' => $notification->data['actions'] ?? null,
                'is_read' => !is_null($notification->read_at),
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
                'time_ago' => $notification->created_at->diffForHumans(),
                'full_data' => $notification->data, // Include full data for debugging/details
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'notification' => $transformedNotification
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Notification show error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'notification_id' => $notificationId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead(Request $request, $notificationId)
    {
        try {
            $user = Auth::user();
            
            $notification = $user->notifications()->where('id', $notificationId)->first();
            
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            // Mark as read if not already read
            if (is_null($notification->read_at)) {
                $notification->markAsRead();
                $wasMarked = true;
            } else {
                $wasMarked = false;
            }

            // Get updated stats
            $stats = [
                'total_count' => $user->notifications()->count(),
                'unread_count' => $user->unreadNotifications()->count(),
                'read_count' => $user->notifications()->whereNotNull('read_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => $wasMarked ? 'Notification marked as read' : 'Notification was already read',
                'data' => [
                    'notification_id' => $notificationId,
                    'was_marked' => $wasMarked,
                    'read_at' => $notification->read_at,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Mark notification as read error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'notification_id' => $notificationId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the current user
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get count of unread notifications before marking
            $unreadCount = $user->unreadNotifications()->count();
            
            if ($unreadCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No unread notifications to mark',
                    'data' => [
                        'marked_count' => 0,
                        'stats' => [
                            'total_count' => $user->notifications()->count(),
                            'unread_count' => 0,
                            'read_count' => $user->notifications()->count(),
                        ]
                    ]
                ]);
            }

            // Mark all unread notifications as read
            $user->unreadNotifications()->update(['read_at' => now()]);

            // Get updated stats
            $stats = [
                'total_count' => $user->notifications()->count(),
                'unread_count' => $user->unreadNotifications()->count(),
                'read_count' => $user->notifications()->whereNotNull('read_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => "Marked {$unreadCount} notifications as read",
                'data' => [
                    'marked_count' => $unreadCount,
                    'marked_at' => now(),
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Mark all notifications as read error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Clear/delete a specific notification
     */
    public function clear(Request $request, $notificationId)
    {
        try {
            $user = Auth::user();
            
            $notification = $user->notifications()->where('id', $notificationId)->first();
            
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            // Store notification info before deletion
            $notificationInfo = [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'was_read' => !is_null($notification->read_at),
            ];

            // Delete the notification
            $notification->delete();

            // Get updated stats
            $stats = [
                'total_count' => $user->notifications()->count(),
                'unread_count' => $user->unreadNotifications()->count(),
                'read_count' => $user->notifications()->whereNotNull('read_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Notification cleared successfully',
                'data' => [
                    'cleared_notification' => $notificationInfo,
                    'cleared_at' => now(),
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Clear notification error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'notification_id' => $notificationId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Clear all notifications for the current user
     */
    public function clearAll(Request $request)
    {
        try {
            $request->validate([
                'confirm' => 'required|boolean|accepted'
            ]);

            $user = Auth::user();
            
            // Get count before deletion
            $totalCount = $user->notifications()->count();
            $unreadCount = $user->unreadNotifications()->count();
            
            if ($totalCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No notifications to clear',
                    'data' => [
                        'cleared_count' => 0,
                        'stats' => [
                            'total_count' => 0,
                            'unread_count' => 0,
                            'read_count' => 0,
                        ]
                    ]
                ]);
            }

            // Delete all notifications for the user
            $user->notifications()->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$totalCount} notifications successfully",
                'data' => [
                    'cleared_count' => $totalCount,
                    'cleared_unread_count' => $unreadCount,
                    'cleared_at' => now(),
                    'stats' => [
                        'total_count' => 0,
                        'unread_count' => 0,
                        'read_count' => 0,
                    ]
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation required to clear all notifications',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Clear all notifications error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear all notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get notification statistics and counts
     */
    public function stats(Request $request)
    {
        try {
            $user = Auth::user();

            // Get basic counts
            $totalCount = $user->notifications()->count();
            $unreadCount = $user->unreadNotifications()->count();
            $readCount = $totalCount - $unreadCount;

            // Get notifications by time periods
            $today = $user->notifications()->whereDate('created_at', today())->count();
            $thisWeek = $user->notifications()->whereBetween('created_at', [
                now()->startOfWeek(), 
                now()->endOfWeek()
            ])->count();
            $thisMonth = $user->notifications()->whereMonth('created_at', now()->month)->count();

            // Get latest notification
            $latestNotification = $user->notifications()->latest()->first();
            $latestNotificationData = null;
            if ($latestNotification) {
                $latestNotificationData = [
                    'id' => $latestNotification->id,
                    'title' => $latestNotification->data['title'] ?? 'Notification',
                    'body' => $latestNotification->data['body'] ?? '',
                    'is_read' => !is_null($latestNotification->read_at),
                    'created_at' => $latestNotification->created_at,
                    'time_ago' => $latestNotification->created_at->diffForHumans(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'counts' => [
                        'total' => $totalCount,
                        'unread' => $unreadCount,
                        'read' => $readCount,
                    ],
                    'time_periods' => [
                        'today' => $today,
                        'this_week' => $thisWeek,
                        'this_month' => $thisMonth,
                    ],
                    'latest_notification' => $latestNotificationData,
                    'has_unread' => $unreadCount > 0,
                    'last_checked' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Notification stats error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a test notification (for development/testing)
     */
    public function createTest(Request $request)
    {
        try {
            // Only allow in development/testing
            if (!config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test notifications only available in debug mode'
                ], 403);
            }

            $request->validate([
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'icon' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:50',
                'url' => 'nullable|url|max:500',
            ]);

            $user = Auth::user();

            // Create test notification
            $user->notify(new \App\Notifications\DatabaseNotification(
                title: $request->input('title'),
                body: $request->input('body'),
                icon: $request->input('icon', 'heroicon-o-bell'),
                color: $request->input('color', 'primary'),
                url: $request->input('url')
            ));

            return response()->json([
                'success' => true,
                'message' => 'Test notification created successfully',
                'data' => [
                    'title' => $request->input('title'),
                    'body' => $request->input('body'),
                    'created_at' => now(),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Create test notification error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create test notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

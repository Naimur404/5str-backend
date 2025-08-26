# Notification API Documentation

## Overview
The Notification API provides comprehensive notification management for users including listing, reading, clearing, and managing notifications with filtering, pagination, and statistics.

## Authentication
All notification endpoints require authentication using Bearer token:
```
Authorization: Bearer {your_access_token}
```

## Base URL
```
/api/v1/notifications
```

---

## API Endpoints

### 1. Get Notification List
**GET** `/api/v1/notifications`

Get paginated list of user's notifications with filtering and search capabilities.

#### Query Parameters:
- `page` (integer, optional): Page number (default: 1)
- `per_page` (integer, optional): Items per page, max 100 (default: 20)
- `filter` (string, optional): Filter notifications - `all`, `read`, `unread` (default: all)
- `search` (string, optional): Search in notification title and body
- `sort_by` (string, optional): Sort field - `created_at`, `read_at`, `type` (default: created_at)
- `sort_order` (string, optional): Sort direction - `asc`, `desc` (default: desc)

#### Example Request:
```http
GET /api/v1/notifications?filter=unread&per_page=10&sort_order=desc
```

#### Example Response:
```json
{
    "success": true,
    "data": {
        "notifications": [
            {
                "id": "uuid-string",
                "type": "App\\Notifications\\DatabaseNotification",
                "title": "New Review Posted",
                "body": "Someone reviewed your business",
                "icon": "heroicon-o-star",
                "color": "success",
                "url": "https://example.com/review/123",
                "actions": null,
                "is_read": false,
                "read_at": null,
                "created_at": "2025-08-26T10:30:00.000000Z",
                "time_ago": "2 hours ago"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 10,
            "total": 25,
            "has_more": true,
            "from": 1,
            "to": 10
        },
        "stats": {
            "total_count": 25,
            "unread_count": 8,
            "read_count": 17
        },
        "filters": {
            "current_filter": "unread",
            "search": null,
            "sort_by": "created_at",
            "sort_order": "desc"
        }
    }
}
```

---

### 2. Get Notification Details
**GET** `/api/v1/notifications/{notification_id}`

Get detailed information about a specific notification.

#### Example Request:
```http
GET /api/v1/notifications/9bb8c8e5-0c42-4f8a-9f8d-1234567890ab
```

#### Example Response:
```json
{
    "success": true,
    "data": {
        "notification": {
            "id": "9bb8c8e5-0c42-4f8a-9f8d-1234567890ab",
            "type": "App\\Notifications\\DatabaseNotification",
            "title": "New Review Posted",
            "body": "Someone reviewed your business Pizza Hut",
            "icon": "heroicon-o-star",
            "color": "success",
            "url": "https://example.com/review/123",
            "actions": null,
            "is_read": false,
            "read_at": null,
            "created_at": "2025-08-26T10:30:00.000000Z",
            "updated_at": "2025-08-26T10:30:00.000000Z",
            "time_ago": "2 hours ago",
            "full_data": {
                "title": "New Review Posted",
                "body": "Someone reviewed your business Pizza Hut",
                "icon": "heroicon-o-star",
                "color": "success",
                "url": "https://example.com/review/123"
            }
        }
    }
}
```

---

### 3. Mark Notification as Read
**PATCH** `/api/v1/notifications/{notification_id}/read`

Mark a specific notification as read.

#### Example Request:
```http
PATCH /api/v1/notifications/9bb8c8e5-0c42-4f8a-9f8d-1234567890ab/read
```

#### Example Response:
```json
{
    "success": true,
    "message": "Notification marked as read",
    "data": {
        "notification_id": "9bb8c8e5-0c42-4f8a-9f8d-1234567890ab",
        "was_marked": true,
        "read_at": "2025-08-26T12:45:00.000000Z",
        "stats": {
            "total_count": 25,
            "unread_count": 7,
            "read_count": 18
        }
    }
}
```

---

### 4. Mark All Notifications as Read
**PATCH** `/api/v1/notifications/mark-all-read`

Mark all unread notifications as read for the current user.

#### Example Request:
```http
PATCH /api/v1/notifications/mark-all-read
```

#### Example Response:
```json
{
    "success": true,
    "message": "Marked 7 notifications as read",
    "data": {
        "marked_count": 7,
        "marked_at": "2025-08-26T12:50:00.000000Z",
        "stats": {
            "total_count": 25,
            "unread_count": 0,
            "read_count": 25
        }
    }
}
```

---

### 5. Clear/Delete Notification
**DELETE** `/api/v1/notifications/{notification_id}`

Delete a specific notification permanently.

#### Example Request:
```http
DELETE /api/v1/notifications/9bb8c8e5-0c42-4f8a-9f8d-1234567890ab
```

#### Example Response:
```json
{
    "success": true,
    "message": "Notification cleared successfully",
    "data": {
        "cleared_notification": {
            "id": "9bb8c8e5-0c42-4f8a-9f8d-1234567890ab",
            "title": "New Review Posted",
            "was_read": false
        },
        "cleared_at": "2025-08-26T12:55:00.000000Z",
        "stats": {
            "total_count": 24,
            "unread_count": 6,
            "read_count": 18
        }
    }
}
```

---

### 6. Clear All Notifications
**DELETE** `/api/v1/notifications`

Delete all notifications for the current user permanently.

#### Request Body:
```json
{
    "confirm": true
}
```

#### Example Request:
```http
DELETE /api/v1/notifications
Content-Type: application/json

{
    "confirm": true
}
```

#### Example Response:
```json
{
    "success": true,
    "message": "Cleared 24 notifications successfully",
    "data": {
        "cleared_count": 24,
        "cleared_unread_count": 6,
        "cleared_at": "2025-08-26T13:00:00.000000Z",
        "stats": {
            "total_count": 0,
            "unread_count": 0,
            "read_count": 0
        }
    }
}
```

---

### 7. Get Notification Statistics
**GET** `/api/v1/notifications/stats`

Get comprehensive notification statistics and counts.

#### Example Request:
```http
GET /api/v1/notifications/stats
```

#### Example Response:
```json
{
    "success": true,
    "data": {
        "counts": {
            "total": 25,
            "unread": 8,
            "read": 17
        },
        "time_periods": {
            "today": 3,
            "this_week": 12,
            "this_month": 25
        },
        "latest_notification": {
            "id": "latest-uuid",
            "title": "New Business Added",
            "body": "A new business was added near you",
            "is_read": false,
            "created_at": "2025-08-26T14:30:00.000000Z",
            "time_ago": "30 minutes ago"
        },
        "has_unread": true,
        "last_checked": "2025-08-26T15:00:00.000000Z"
    }
}
```

---

### 8. Create Test Notification (Development Only)
**POST** `/api/v1/notifications/test`

Create a test notification for development/testing purposes. Only available when `APP_DEBUG=true`.

#### Request Body:
```json
{
    "title": "Test Notification",
    "body": "This is a test notification message",
    "icon": "heroicon-o-bell",
    "color": "primary",
    "url": "https://example.com/test"
}
```

#### Example Request:
```http
POST /api/v1/notifications/test
Content-Type: application/json

{
    "title": "Test Notification",
    "body": "This is a test notification message",
    "icon": "heroicon-o-star",
    "color": "success",
    "url": "https://example.com/test"
}
```

#### Example Response:
```json
{
    "success": true,
    "message": "Test notification created successfully",
    "data": {
        "title": "Test Notification",
        "body": "This is a test notification message",
        "created_at": "2025-08-26T15:05:00.000000Z"
    }
}
```

---

## Error Responses

### Validation Error (422):
```json
{
    "success": false,
    "message": "Invalid input parameters",
    "errors": {
        "per_page": ["The per page must not be greater than 100."],
        "filter": ["The selected filter is invalid."]
    }
}
```

### Not Found Error (404):
```json
{
    "success": false,
    "message": "Notification not found"
}
```

### Authentication Error (401):
```json
{
    "success": false,
    "message": "Unauthenticated"
}
```

### Server Error (500):
```json
{
    "success": false,
    "message": "Failed to fetch notifications",
    "error": "Internal server error"
}
```

---

## Mobile App Integration Examples

### React Native Example:
```javascript
// Get notifications list
const getNotifications = async (filter = 'all', page = 1) => {
    try {
        const response = await fetch(`${API_BASE}/api/v1/notifications?filter=${filter}&page=${page}`, {
            headers: {
                'Authorization': `Bearer ${userToken}`,
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
};

// Mark all as read
const markAllAsRead = async () => {
    try {
        const response = await fetch(`${API_BASE}/api/v1/notifications/mark-all-read`, {
            method: 'PATCH',
            headers: {
                'Authorization': `Bearer ${userToken}`,
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error marking notifications as read:', error);
    }
};

// Clear all notifications
const clearAllNotifications = async () => {
    try {
        const response = await fetch(`${API_BASE}/api/v1/notifications`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${userToken}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ confirm: true })
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error clearing notifications:', error);
    }
};
```

### Flutter/Dart Example:
```dart
class NotificationService {
    final String baseUrl;
    final String token;
    
    NotificationService(this.baseUrl, this.token);
    
    Future<Map<String, dynamic>> getNotifications({
        String filter = 'all',
        int page = 1,
        int perPage = 20
    }) async {
        final response = await http.get(
            Uri.parse('$baseUrl/api/v1/notifications')
                .replace(queryParameters: {
                    'filter': filter,
                    'page': page.toString(),
                    'per_page': perPage.toString(),
                }),
            headers: {
                'Authorization': 'Bearer $token',
                'Content-Type': 'application/json',
            },
        );
        
        return json.decode(response.body);
    }
    
    Future<Map<String, dynamic>> markAsRead(String notificationId) async {
        final response = await http.patch(
            Uri.parse('$baseUrl/api/v1/notifications/$notificationId/read'),
            headers: {
                'Authorization': 'Bearer $token',
                'Content-Type': 'application/json',
            },
        );
        
        return json.decode(response.body);
    }
}
```

---

## Best Practices

### 1. Pagination:
- Use reasonable page sizes (10-50 items)
- Implement infinite scrolling for better UX
- Cache previous pages to improve performance

### 2. Real-time Updates:
- Poll the stats endpoint every 30-60 seconds
- Show notification badges with unread counts
- Refresh list when app comes to foreground

### 3. User Experience:
- Show loading states during API calls
- Implement optimistic UI updates for mark as read
- Provide confirmation dialogs for destructive actions (clear all)

### 4. Performance:
- Use the filter parameter to reduce payload size
- Implement proper error handling and retry logic
- Consider implementing offline support for read notifications

### 5. Security:
- Always include authentication headers
- Validate user permissions server-side
- Don't store sensitive data in notification content

---

## Notification Types

The system supports various notification types:
- **Business Reviews**: New reviews on user's businesses
- **Trending Updates**: Business trending status changes
- **System Announcements**: App updates, maintenance notices
- **Promotional**: Special offers, featured business alerts
- **Social**: User interactions, follows, mentions

Each notification includes:
- **Title**: Short descriptive title
- **Body**: Detailed message content
- **Icon**: Heroicon for visual representation
- **Color**: Theme color (primary, success, warning, danger)
- **URL**: Optional deep link for navigation
- **Actions**: Optional action buttons (future feature)

---

## Rate Limits

- **Read operations**: 100 requests per minute
- **Write operations**: 30 requests per minute
- **Test notifications**: 5 requests per minute (debug mode only)

Exceeded rate limits return HTTP 429 with retry information.

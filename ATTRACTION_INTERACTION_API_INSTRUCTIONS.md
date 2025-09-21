# Attraction Interaction API - Instructions, Payloads & Responses

This document provides clear instructions on how to use the attraction interaction APIs along with request payloads and response formats.

## Authentication
All endpoints require Bearer token authentication in the header:
```
Authorization: Bearer {your-auth-token}
```

---

## 1. Store New Interaction

**Endpoint:** `POST /api/v1/attraction-interactions`

### Instructions:
- Use this endpoint to create any type of interaction (like, bookmark, share, visit, wishlist)
- All fields except `attraction_id` and `interaction_type` are optional
- Different interaction types support different optional fields

### Request Payloads:

#### Like Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "like",
  "notes": "Amazing place with beautiful scenery!",
  "is_public": true
}
```

#### Bookmark Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "bookmark",
  "notes": "Perfect for weekend getaway",
  "priority": "high",
  "planned_visit_date": "2025-12-15",
  "is_public": false
}
```

#### Visit Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "visit",
  "visit_date": "2025-09-15",
  "duration_minutes": 240,
  "companions": ["family", "friends"],
  "weather": "sunny",
  "rating": 4.5,
  "notes": "Had an incredible time! Perfect weather.",
  "is_public": true
}
```

#### Share Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "share",
  "platform": "facebook",
  "message": "Check out this amazing destination!",
  "is_public": true
}
```

#### Wishlist Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "wishlist",
  "priority": "medium",
  "planned_visit_date": "2026-01-20",
  "notes": "Planning for New Year celebration",
  "is_public": true
}
```

### Response Format:
```json
{
  "success": true,
  "message": "Interaction recorded successfully",
  "data": {
    "interaction": {
      "id": 25,
      "user_id": 2,
      "attraction_id": 3,
      "interaction_type": "visit",
      "interaction_data": {
        "visit_date": "2025-09-15",
        "duration_minutes": 240,
        "companions": ["family", "friends"],
        "weather": "sunny"
      },
      "notes": "Had an incredible time!",
      "user_rating": 4.5,
      "is_public": true,
      "is_active": true,
      "interaction_date": "2025-09-21T10:30:00.000000Z",
      "created_at": "2025-09-21T10:30:00.000000Z",
      "updated_at": "2025-09-21T10:30:00.000000Z"
    },
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "total_likes": 12,
      "total_shares": 5
    }
  }
}
```

---

## 2. Toggle Interaction

**Endpoint:** `POST /api/v1/attraction-interactions/toggle`

### Instructions:
- Use this endpoint to toggle like/bookmark/wishlist interactions on/off
- If active interaction exists, it will be deactivated (removed)
- If no active interaction exists, it will create a new one or reactivate existing inactive interaction
- Only supports: like, dislike, bookmark, wishlist
- **Note**: The system maintains unique constraints, so if you get a duplicate entry error, the interaction already exists and will be reactivated instead

### Request Payload:
```json
{
  "attraction_id": 3,
  "interaction_type": "like",
  "notes": "Changed my mind - this place is fantastic!",
  "is_public": true
}
```

### Response Format (Creating):
```json
{
  "success": true,
  "message": "Attraction liked",
  "data": {
    "action": "created",
    "is_liked": true,
    "interaction": {
      "id": 26,
      "user_id": 2,
      "attraction_id": 3,
      "interaction_type": "like",
      "notes": "Changed my mind - this place is fantastic!",
      "is_public": true,
      "interaction_date": "2025-09-21T10:35:00.000000Z"
    },
    "attraction_stats": {
      "total_likes": 13,
      "total_dislikes": 0
    }
  }
}
```

### Response Format (Removing):
```json
{
  "success": true,
  "message": "Like removed",
  "data": {
    "action": "removed",
    "is_liked": false,
    "interaction": null,
    "attraction_stats": {
      "total_likes": 12,
      "total_dislikes": 0
    }
  }
}
```

---

## 3. Remove Interaction

**Endpoint:** `DELETE /api/v1/attraction-interactions/remove`

### Instructions:
- Use this endpoint to permanently remove a specific interaction
- Requires both attraction_id and interaction_type
- Returns success even if interaction doesn't exist

### Request Payload:
```json
{
  "attraction_id": 3,
  "interaction_type": "bookmark"
}
```

### Response Format:
```json
{
  "success": true,
  "message": "Interaction removed successfully"
}
```

### Error Response (Not Found):
```json
{
  "success": false,
  "message": "Interaction not found"
}
```

---

## 4. Get User Interactions

**Endpoint:** `GET /api/v1/attraction-interactions/user/{userId}`

### Instructions:
- Retrieve all interactions for a specific user
- Use query parameters to filter and paginate
- Available filters: interaction_type, per_page, page

### Query Parameters:
- `interaction_type` (optional): like, bookmark, visit, share, wishlist
- `per_page` (optional): Number of items per page (default: 15)
- `page` (optional): Page number (default: 1)

### Example Request:
```
GET /api/v1/attraction-interactions/user/2?interaction_type=like&per_page=10&page=1
```

### Response Format:
```json
{
  "success": true,
  "message": "User interactions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 25,
        "user_id": 2,
        "attraction_id": 3,
        "interaction_type": "like",
        "notes": "Amazing place!",
        "is_public": true,
        "interaction_date": "2025-09-21T10:30:00.000000Z",
        "attraction": {
          "id": 3,
          "name": "Cox's Bazar Beach",
          "slug": "coxs-bazar-beach",
          "cover_image_url": "https://picsum.photos/800/600?random=31",
          "overall_rating": "4.38",
          "category": "Beach",
          "city": "Cox's Bazar",
          "is_free": true
        }
      }
    ],
    "first_page_url": "http://localhost:8000/api/v1/attraction-interactions/user/2?page=1",
    "per_page": 10,
    "total": 1
  }
}
```

---

## 5. Get Attraction Interactions

**Endpoint:** `GET /api/v1/attraction-interactions/attraction/{attractionId}`

### Instructions:
- Get all public interactions for a specific attraction
- Shows interactions from all users
- Use query parameters to filter by interaction type and paginate

### Query Parameters:
- `interaction_type` (optional): Filter by specific interaction type
- `per_page` (optional): Items per page (default: 20)
- `page` (optional): Page number

### Example Request:
```
GET /api/v1/attraction-interactions/attraction/3?interaction_type=like&per_page=20
```

### Response Format:
```json
{
  "success": true,
  "message": "Attraction interactions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 25,
        "user_id": 2,
        "attraction_id": 3,
        "interaction_type": "like",
        "notes": "Beautiful location!",
        "is_public": true,
        "interaction_date": "2025-09-21T10:30:00.000000Z",
        "user": {
          "id": 2,
          "name": "John Doe",
          "profile_image": null,
          "trust_level": 1
        }
      }
    ],
    "per_page": 20,
    "total": 15
  },
  "meta": {
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "total_likes": 12,
      "total_shares": 5
    }
  }
}
```

---

## 6. Get User's Liked Attractions

**Endpoint:** `GET /api/v1/attraction-interactions/liked`

### Instructions:
- Get current user's liked attractions
- Returns interactions with full attraction details
- Supports pagination

### Query Parameters:
- `per_page` (optional): Items per page (default: 15)
- `page` (optional): Page number

### Example Request:
```
GET /api/v1/attraction-interactions/liked?per_page=12&page=1
```

### Response Format:
```json
{
  "success": true,
  "message": "Liked attractions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 25,
        "user_id": 2,
        "attraction_id": 3,
        "interaction_type": "like",
        "notes": "Beautiful location!",
        "created_at": "2025-09-21T10:30:00.000000Z",
        "attraction": {
          "id": 3,
          "name": "Cox's Bazar Beach",
          "slug": "coxs-bazar-beach",
          "description": "World's longest natural sandy sea beach...",
          "category": "Beach",
          "city": "Cox's Bazar",
          "overall_rating": "4.38",
          "is_free": true,
          "cover_image_url": "https://picsum.photos/800/600?random=31",
          "gallery": [
            {
              "id": 1,
              "image_url": "https://picsum.photos/800/600?random=31",
              "title": "Main View"
            }
          ]
        }
      }
    ],
    "per_page": 12,
    "total": 8
  }
}
```

---

## 7. Get User's Bookmarked Attractions

**Endpoint:** `GET /api/v1/attraction-interactions/bookmarked`

### Instructions:
- Get current user's bookmarked attractions
- Same response format as liked attractions
- May include bookmark-specific data like priority and planned_visit_date in interaction_data

### Query Parameters:
- `per_page` (optional): Items per page (default: 15)
- `page` (optional): Page number

### Response Format:
Same as liked attractions, but `interaction_type` will be "bookmark" and may include additional bookmark data in the `interaction_data` field.

---

## 8. Get User's Visited Attractions

**Endpoint:** `GET /api/v1/attraction-interactions/visited`

### Instructions:
- Get current user's visited attractions
- Ordered by interaction_date (most recent first)
- May include visit-specific data like duration, companions, weather in interaction_data

### Query Parameters:
- `per_page` (optional): Items per page (default: 15)
- `page` (optional): Page number

### Response Format:
Same as liked attractions, but `interaction_type` will be "visit" and may include visit details in the `interaction_data` field.

---

## Field Descriptions

### Interaction Types:
- `like` - User likes the attraction
- `dislike` - User dislikes the attraction  
- `bookmark` - User saves attraction for later
- `share` - User shares the attraction
- `visit` - User records a visit to the attraction
- `wishlist` - User adds attraction to wishlist

### Optional Fields by Type:

#### For All Types:
- `notes` - User notes (max 1000 characters)
- `is_public` - Whether interaction is public (default: true)

#### For Visit:
- `visit_date` - Date of visit (YYYY-MM-DD, max today)
- `duration_minutes` - Visit duration (15-1440 minutes)
- `companions` - Array of companion types: solo, partner, friend, family, group, business
- `weather` - Weather during visit
- `rating` - User rating (0-5)

#### For Share:
- `platform` - Platform shared to (max 50 characters)
- `message` - Custom message (max 500 characters)

#### For Bookmark/Wishlist:
- `priority` - Priority level: low, medium, high
- `planned_visit_date` - Planned visit date (future date)

---

## Common Error Responses

### Validation Error (422):
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "attraction_id": ["The attraction id field is required."],
    "interaction_type": ["The selected interaction type is invalid."]
  }
}
```

### Unauthorized (401):
```json
{
  "message": "Unauthenticated."
}
```

### Not Found (404):
```json
{
  "success": false,
  "message": "Attraction not found"
}
```

### Server Error (500):
```json
{
  "success": false,
  "message": "Failed to record interaction",
  "error": "Database connection failed"
}
```

### Database Constraint Error (Fixed):
If you encounter a "Duplicate entry" error for the toggle endpoint, this indicates the system found an existing inactive interaction and will reactivate it instead of creating a new one. This is automatically handled by the API.

---

## 9. Check User Interaction Status

**Endpoint:** `GET /api/v1/attraction-interactions/status/{attractionId}`

### Instructions:
- Check if the current authenticated user has any interactions with a specific attraction
- Returns boolean flags for each interaction type (has_liked, has_bookmarked, etc.)
- Also returns detailed information about existing interactions
- Use this endpoint to show correct UI state (filled heart for liked, etc.)

### Example Request:
```
GET /api/v1/attraction-interactions/status/3
```

### Response Format:
```json
{
  "success": true,
  "message": "User interaction status retrieved successfully",
  "data": {
    "attraction_id": 3,
    "user_id": 15,
    "interaction_status": {
      "has_liked": true,
      "has_disliked": false,
      "has_bookmarked": true,
      "has_visited": false,
      "has_shared": true,
      "has_wishlisted": false,
      "interaction_details": [
        {
          "id": 25,
          "interaction_type": "like",
          "notes": "Amazing place!",
          "user_rating": null,
          "interaction_data": null,
          "is_public": true,
          "interaction_date": "2025-09-21T10:30:00.000000Z",
          "created_at": "2025-09-21T10:30:00.000000Z"
        },
        {
          "id": 26,
          "interaction_type": "bookmark",
          "notes": "Perfect for weekend trip",
          "user_rating": null,
          "interaction_data": {
            "priority": "high",
            "planned_visit_date": "2025-12-15"
          },
          "is_public": false,
          "interaction_date": "2025-09-21T11:15:00.000000Z",
          "created_at": "2025-09-21T11:15:00.000000Z"
        },
        {
          "id": 27,
          "interaction_type": "share",
          "notes": null,
          "user_rating": null,
          "interaction_data": {
            "platform": "facebook",
            "message": "Check this out!",
            "shared_at": "2025-09-21T12:00:00.000000Z"
          },
          "is_public": true,
          "interaction_date": "2025-09-21T12:00:00.000000Z",
          "created_at": "2025-09-21T12:00:00.000000Z"
        }
      ]
    },
    "total_interactions": 3
  }
}
```

### Response Format (No Interactions):
```json
{
  "success": true,
  "message": "User interaction status retrieved successfully",
  "data": {
    "attraction_id": 3,
    "user_id": 15,
    "interaction_status": {
      "has_liked": false,
      "has_disliked": false,
      "has_bookmarked": false,
      "has_visited": false,
      "has_shared": false,
      "has_wishlisted": false,
      "interaction_details": []
    },
    "total_interactions": 0
  }
}
```

---

## Usage Instructions

1. **Authentication**: Always include Bearer token in Authorization header
2. **Content Type**: Use `application/json` for POST/PUT requests
3. **Error Handling**: Check `success` field in response, handle errors gracefully
4. **Pagination**: Use `page` and `per_page` parameters for list endpoints
5. **Filtering**: Use `interaction_type` parameter to filter specific interactions
6. **Rate Limiting**: Implement client-side rate limiting to avoid API abuse
7. **Caching**: Cache responses appropriately to reduce API calls
8. **Offline Support**: Store interactions locally and sync when online
9. **Duplicate Handling**: The toggle endpoint automatically handles duplicate entries by reactivating existing interactions
10. **Status Checking**: Use the status endpoint to determine current user interaction state before showing UI elements
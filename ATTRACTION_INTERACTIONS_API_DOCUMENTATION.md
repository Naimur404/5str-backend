# Attraction Interactions API Documentation

This document provides comprehensive request payloads and response formats for all attraction interaction endpoints.

## Authentication Required
All endpoints require Bearer token authentication:
```
Authorization: Bearer {your-token-here}
```

## 1. Store Interaction API

### Endpoint
```
POST /api/v1/attraction-interactions
```

### Request Payload
```json
{
  "attraction_id": 3,
  "interaction_type": "like",
  "notes": "Amazing place! Perfect for sunset photography.",
  "is_public": true
}
```

### Request Payload for Different Interaction Types

#### Like/Dislike Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "like",
  "notes": "Beautiful location!",
  "is_public": true
}
```

#### Bookmark Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "bookmark",
  "notes": "Want to visit during winter",
  "priority": "high",
  "planned_visit_date": "2024-12-15",
  "is_public": false
}
```

#### Share Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "share",
  "platform": "facebook",
  "message": "Check out this amazing beach! Perfect for vacation.",
  "is_public": true
}
```

#### Visit Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "visit",
  "visit_date": "2024-09-15",
  "duration_minutes": 300,
  "companions": ["friend", "family"],
  "weather": "sunny",
  "rating": 4.5,
  "notes": "Had a wonderful time with family. Weather was perfect!",
  "is_public": true
}
```

#### Wishlist Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "wishlist",
  "priority": "medium",
  "planned_visit_date": "2025-01-01",
  "notes": "Want to visit for New Year celebration",
  "is_public": true
}
```

### Field Descriptions
- `attraction_id` (required): ID of the attraction
- `interaction_type` (required): One of: `like`, `dislike`, `bookmark`, `share`, `visit`, `wishlist`
- `notes` (optional): User notes, max 1000 characters
- `visit_date` (optional): For visit interactions, date in YYYY-MM-DD format
- `rating` (optional): For visit interactions, rating from 0-5
- `platform` (optional): For share interactions: `facebook`, `twitter`, `instagram`, `whatsapp`, `email`, `sms`, `copy_link`
- `message` (optional): For share interactions, custom message, max 500 characters
- `is_public` (optional): Whether interaction is public, default true
- `duration_minutes` (optional): For visit interactions, visit duration
- `companions` (optional): Array of companion types for visit interactions
- `priority` (optional): For bookmark/wishlist: `low`, `medium`, `high`
- `planned_visit_date` (optional): For bookmark/wishlist interactions

### Response Format
```json
{
  "success": true,
  "message": "Interaction recorded successfully",
  "data": {
    "interaction": {
      "id": 15,
      "user_id": 2,
      "attraction_id": 3,
      "interaction_type": "like",
      "interaction_data": null,
      "notes": "Beautiful location!",
      "user_rating": null,
      "is_public": true,
      "is_active": true,
      "interaction_date": "2025-09-21T10:30:00.000000Z",
      "created_at": "2025-09-21T10:30:00.000000Z",
      "updated_at": "2025-09-21T10:30:00.000000Z"
    },
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "total_likes": 11,
      "total_shares": 0
    }
  }
}
```

## 2. Toggle Interaction API

### Endpoint
```
POST /api/v1/attraction-interactions/toggle
```

### Request Payload
```json
{
  "attraction_id": 3,
  "interaction_type": "like",
  "notes": "Changed my mind about this place",
  "is_public": true
}
```

### Response Format (First toggle - creates interaction)
```json
{
  "success": true,
  "message": "Attraction liked",
  "data": {
    "action": "created",
    "is_liked": true,
    "interaction": {
      "id": 16,
      "user_id": 2,
      "attraction_id": 3,
      "interaction_type": "like",
      "notes": "Amazing place!",
      "is_public": true,
      "created_at": "2025-09-21T10:35:00.000000Z"
    },
    "attraction_stats": {
      "total_likes": 12,
      "total_dislikes": 0
    }
  }
}
```

### Response Format (Second toggle - removes interaction)
```json
{
  "success": true,
  "message": "Like removed",
  "data": {
    "action": "removed",
    "is_liked": false,
    "interaction": null,
    "attraction_stats": {
      "total_likes": 11,
      "total_dislikes": 0
    }
  }
}
```

## 3. Remove Interaction API

### Endpoint
```
DELETE /api/v1/attraction-interactions/remove
```

### Request Payload
```json
{
  "attraction_id": 3,
  "interaction_type": "bookmark"
}
```

### Response Format
```json
{
  "success": true,
  "message": "Interaction removed successfully"
}
```

### Error Response (Interaction not found)
```json
{
  "success": false,
  "message": "Interaction not found"
}
```

## 4. Get User Interactions API

### Endpoint
```
GET /api/v1/attraction-interactions/user/{userId}
```

### Query Parameters
- `interaction_type` (optional): Filter by interaction type
- `per_page` (optional): Items per page, default 15
- `page` (optional): Page number

### Example Request
```
GET /api/v1/attraction-interactions/user/2?interaction_type=like&per_page=10&page=1
```

### Response Format
```json
{
  "success": true,
  "message": "User interactions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 15,
        "user_id": 2,
        "attraction_id": 3,
        "interaction_type": "like",
        "notes": "Beautiful location!",
        "is_public": true,
        "interaction_date": "2025-09-21T10:30:00.000000Z",
        "created_at": "2025-09-21T10:30:00.000000Z",
        "attraction": {
          "id": 3,
          "name": "Cox's Bazar Beach",
          "slug": "coxs-bazar-beach",
          "cover_image_url": "https://picsum.photos/800/600?random=31",
          "overall_rating": "4.38",
          "category": "Beach",
          "city": "Cox's Bazar",
          "is_free": true,
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
    "first_page_url": "http://localhost:8000/api/v1/attraction-interactions/user/2?page=1",
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

## 5. Get Attraction Interactions API

### Endpoint
```
GET /api/v1/attraction-interactions/attraction/{attractionId}
```

### Query Parameters
- `interaction_type` (optional): Filter by interaction type
- `per_page` (optional): Items per page, default 20
- `page` (optional): Page number

### Response Format
```json
{
  "success": true,
  "message": "Attraction interactions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 15,
        "user_id": 2,
        "attraction_id": 3,
        "interaction_type": "like",
        "notes": "Beautiful location!",
        "is_public": true,
        "interaction_date": "2025-09-21T10:30:00.000000Z",
        "created_at": "2025-09-21T10:30:00.000000Z",
        "user": {
          "id": 2,
          "name": "Mr. Nathanial Pollich MD",
          "profile_image": null,
          "trust_level": 1
        }
      }
    ],
    "per_page": 20,
    "total": 1
  },
  "meta": {
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "total_likes": 11,
      "total_shares": 0
    }
  }
}
```

## 6. Get Liked Attractions API

### Endpoint
```
GET /api/v1/attraction-interactions/liked
```

### Query Parameters
- `per_page` (optional): Items per page, default 15
- `page` (optional): Page number

### Response Format
```json
{
  "success": true,
  "message": "Liked attractions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 15,
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
    "per_page": 15,
    "total": 1
  }
}
```

## 7. Get Bookmarked Attractions API

### Endpoint
```
GET /api/v1/attraction-interactions/bookmarked
```

### Response Format
Same structure as liked attractions, but filtered for `bookmark` interactions.

## 8. Get Visited Attractions API

### Endpoint
```
GET /api/v1/attraction-interactions/visited
```

### Response Format
Same structure as liked attractions, but filtered for `visit` interactions and ordered by `interaction_date`.

## Error Response Examples

### Validation Error (422)
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

### Unauthorized Access (403)
```json
{
  "success": false,
  "message": "Unauthorized access"
}
```

### Attraction Not Found (404)
```json
{
  "success": false,
  "message": "Attraction not found",
  "error": "No query results for model [App\\Models\\Attraction] 999"
}
```

### Authentication Required (401)
```json
{
  "message": "Unauthenticated."
}
```

## Frontend Integration Examples

### Like/Unlike an Attraction
```javascript
const toggleLike = async (attractionId) => {
  try {
    const response = await fetch('/api/v1/attraction-interactions/toggle', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        attraction_id: attractionId,
        interaction_type: 'like'
      })
    });

    const result = await response.json();
    
    if (result.success) {
      return {
        isLiked: result.data.is_liked,
        totalLikes: result.data.attraction_stats.total_likes
      };
    } else {
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('Like toggle failed:', error);
    throw error;
  }
};
```

### Bookmark an Attraction
```javascript
const bookmarkAttraction = async (attractionId, notes = '', priority = 'medium') => {
  try {
    const response = await fetch('/api/v1/attraction-interactions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        attraction_id: attractionId,
        interaction_type: 'bookmark',
        notes: notes,
        priority: priority,
        is_public: false
      })
    });

    const result = await response.json();
    return result.data;
  } catch (error) {
    console.error('Bookmark failed:', error);
    throw error;
  }
};
```

### Record a Visit
```javascript
const recordVisit = async (attractionId, visitData) => {
  try {
    const response = await fetch('/api/v1/attraction-interactions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        attraction_id: attractionId,
        interaction_type: 'visit',
        visit_date: visitData.visitDate,
        duration_minutes: visitData.duration,
        companions: visitData.companions,
        rating: visitData.rating,
        notes: visitData.notes,
        weather: visitData.weather
      })
    });

    const result = await response.json();
    return result.data;
  } catch (error) {
    console.error('Visit recording failed:', error);
    throw error;
  }
};
```

### Get User's Liked Attractions
```javascript
const getLikedAttractions = async (page = 1, perPage = 15) => {
  try {
    const response = await fetch(`/api/v1/attraction-interactions/liked?page=${page}&per_page=${perPage}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });

    const result = await response.json();
    return result.data;
  } catch (error) {
    console.error('Failed to get liked attractions:', error);
    throw error;
  }
};
```
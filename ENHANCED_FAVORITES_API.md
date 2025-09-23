# Enhanced Favorites API Documentation

## Overview
The favorites API now supports three types of favorites:
1. **Business Favorites** - Traditional business favorites
2. **Offering Favorites** - Business offering favorites  
3. **Attraction Favorites** - Attraction interactions (bookmarks, likes, wishlist)

## API Endpoints

### 1. Get User Favorites
**GET** `/api/v1/user/favorites`

**Query Parameters:**
- `type` (optional): `business`, `offering`, `attraction`, or omit for all
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 20)

**Response Format:**
```json
{
    "success": true,
    "data": {
        "favorites": [
            {
                "id": 1,
                "type": "business",
                "favorited_at": "2025-09-23 12:30:00",
                "business": {
                    "id": 1,
                    "business_name": "Amazing Restaurant",
                    "slug": "amazing-restaurant",
                    "landmark": "Near City Center",
                    "overall_rating": 4.5,
                    "total_reviews": 120,
                    "price_range": "$$",
                    "category_name": "Restaurant",
                    "logo_image": "https://example.com/logo.jpg"
                }
            },
            {
                "id": 2,
                "type": "offering",
                "favorited_at": "2025-09-23 12:25:00",
                "offering": {
                    "id": 15,
                    "name": "Special Pizza",
                    "business_id": 1,
                    "offering_type": "food",
                    "price_range": "$",
                    "average_rating": 4.8,
                    "total_reviews": 45,
                    "business_name": "Amazing Restaurant",
                    "image_url": "https://example.com/pizza.jpg"
                }
            },
            {
                "id": 3,
                "type": "attraction",
                "interaction_type": "bookmark",
                "favorited_at": "2025-09-23 12:20:00",
                "attraction": {
                    "id": 25,
                    "name": "Historical Museum",
                    "slug": "historical-museum",
                    "type": "museum",
                    "category": "cultural",
                    "address": "123 Museum Street",
                    "city": "Dhaka",
                    "area": "Dhanmondi",
                    "overall_rating": 4.3,
                    "total_reviews": 89,
                    "total_likes": 245,
                    "is_free": false,
                    "entry_fee": 50.00,
                    "currency": "BDT",
                    "estimated_duration_minutes": 120,
                    "difficulty_level": "easy",
                    "cover_image": "https://example.com/museum.jpg",
                    "is_verified": true,
                    "is_featured": false
                },
                "notes": "Want to visit during weekend",
                "user_rating": 4.0
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 20,
            "total": 45,
            "has_more": true
        }
    }
}
```

### 2. Add to Favorites
**POST** `/api/v1/user/favorites`

#### For Business/Offering:
```json
{
    "favoritable_type": "business",
    "favoritable_id": 123
}
```

#### For Attractions:
```json
{
    "favoritable_type": "attraction",
    "favoritable_id": 456,
    "interaction_type": "bookmark",
    "notes": "Want to visit next weekend",
    "is_public": true,
    "priority": "high",
    "planned_visit_date": "2025-10-15"
}
```

**Attraction Parameters:**
- `interaction_type` (required): `bookmark`, `like`, or `wishlist`
- `notes` (optional): User notes about the attraction
- `is_public` (optional): Whether the favorite is public (default: true)
- `priority` (optional): `low`, `medium`, `high` (for bookmarks/wishlist)
- `planned_visit_date` (optional): Future date for planned visits

**Success Response:**
```json
{
    "success": true,
    "message": "Attraction bookmarked successfully",
    "data": {
        "interaction_id": 789,
        "type": "attraction",
        "interaction_type": "bookmark"
    }
}
```

### 3. Remove from Favorites
**DELETE** `/api/v1/user/favorites/{favoriteId}`

Works for both traditional favorites and attraction interactions using the same ID returned from the favorites list.

**Response:**
```json
{
    "success": true,
    "message": "Removed from favorites"
}
```

## Query Examples

### Get All Favorites
```
GET /api/v1/user/favorites
```

### Get Only Business Favorites
```
GET /api/v1/user/favorites?type=business
```

### Get Only Attraction Favorites
```
GET /api/v1/user/favorites?type=attraction
```

### Get Paginated Results
```
GET /api/v1/user/favorites?page=2&limit=10
```

## Attraction Interaction Types

1. **bookmark** - Save for later viewing
2. **like** - Express positive sentiment
3. **wishlist** - Places user wants to visit

Each type supports additional metadata like notes, priority, and planned visit dates.

## Integration Notes

- All favorites are returned in chronological order (newest first)
- Attraction favorites include rich metadata from the UserAttractionInteraction system
- The API maintains backward compatibility with existing business/offering favorites
- Pagination works consistently across all favorite types
- The same remove endpoint works for all favorite types using their respective IDs

## Authentication

All endpoints require authentication via Bearer token:
```
Authorization: Bearer your-auth-token
```
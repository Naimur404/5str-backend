# User Collections API Documentation

## Overview
The User Collections feature allows users to create custom lists of businesses (like playlists for businesses). Users can:
- Create personal collections (private or public)
- Add/remove businesses to/from collections
- Follow other users' public collections
- Discover popular collections
- Search for collections

## Authentication
All collection management endpoints require authentication using Bearer token in the Authorization header:
```
Authorization: Bearer {your_token_here}
```

## API Endpoints

### 1. User's Collections Management

#### GET `/api/v1/collections`
Get all collections created by the authenticated user.

**Response:**
```json
{
  "success": true,
  "data": {
    "collections": [
      {
        "id": 1,
        "name": "My Favorite Restaurants",
        "description": "Best places I've tried",
        "is_public": true,
        "cover_image": "https://example.com/image.jpg",
        "slug": "my-favorite-restaurants-1635123456",
        "businesses_count": 5,
        "followers_count": 12,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z",
        "businesses": [
          {
            "id": 1,
            "name": "Restaurant ABC",
            "phone": "+8801234567890",
            "address": "123 Main St, Dhaka",
            "image_url": "https://example.com/restaurant.jpg"
          }
        ]
      }
    ]
  }
}
```

#### POST `/api/v1/collections`
Create a new collection.

**Request Body:**
```json
{
  "name": "My Favorite Restaurants",
  "description": "Best places I've tried",
  "is_public": true,
  "cover_image": "https://example.com/image.jpg"
}
```

**Response:** Same as single collection object above.

#### GET `/api/v1/collections/{collection_id}`
Get details of a specific collection (public collections or user's own collections).

**Response:** Single collection object with detailed business information.

#### PUT `/api/v1/collections/{collection_id}`
Update a collection (only collection owner can update).

**Request Body:** Same as create request.

#### DELETE `/api/v1/collections/{collection_id}`
Delete a collection (only collection owner can delete).

**Response:**
```json
{
  "success": true,
  "message": "Collection deleted successfully"
}
```

### 2. Collection Business Management

#### POST `/api/v1/collections/{collection_id}/businesses`
Add a business to a collection.

**Request Body:**
```json
{
  "business_id": 123,
  "notes": "Great food and service",
  "sort_order": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Business added to collection successfully",
  "data": {
    "collection_item": {
      "id": 1,
      "collection_id": 1,
      "business_id": 123,
      "notes": "Great food and service",
      "sort_order": 1,
      "added_at": "2024-01-15T10:30:00Z",
      "business": {
        "id": 123,
        "name": "Restaurant ABC",
        "phone": "+8801234567890",
        "address": "123 Main St, Dhaka",
        "image_url": "https://example.com/restaurant.jpg",
        "rating": 4.5
      }
    }
  }
}
```

#### DELETE `/api/v1/collections/{collection_id}/businesses/{business_id}`
Remove a business from a collection.

**Response:**
```json
{
  "success": true,
  "message": "Business removed from collection successfully"
}
```

### 3. Collection Following System

#### POST `/api/v1/collections/{collection_id}/follow`
Follow a public collection.

**Response:**
```json
{
  "success": true,
  "message": "Collection followed successfully"
}
```

#### DELETE `/api/v1/collections/{collection_id}/follow`
Unfollow a collection.

**Response:**
```json
{
  "success": true,
  "message": "Collection unfollowed successfully"
}
```

### 4. Public Collection Discovery

#### GET `/api/v1/discover/collections/popular`
Get popular public collections (no authentication required).

**Query Parameters:**
- `limit` (optional): Maximum number of results (default: 10, max: 50)

**Response:**
```json
{
  "success": true,
  "data": {
    "collections": [
      {
        "id": 1,
        "name": "Best Restaurants in Dhaka",
        "description": "Top-rated restaurants in the capital",
        "is_public": true,
        "cover_image": "https://example.com/image.jpg",
        "businesses_count": 15,
        "followers_count": 245,
        "user": {
          "id": 1,
          "name": "John Doe"
        },
        "businesses": [
          {
            "id": 1,
            "name": "Restaurant ABC",
            "image_url": "https://example.com/restaurant.jpg"
          }
        ]
      }
    ]
  }
}
```

#### GET `/api/v1/discover/collections/search`
Search public collections (no authentication required).

**Query Parameters:**
- `query` (required): Search term (min: 2 chars, max: 100 chars)
- `limit` (optional): Maximum number of results (default: 10, max: 50)

**Response:** Same format as popular collections.

## Error Responses

All endpoints return standardized error responses:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Common HTTP Status Codes:
- `200`: Success
- `201`: Created successfully
- `400`: Bad request / Validation errors
- `401`: Unauthorized (invalid or missing token)
- `403`: Forbidden (insufficient permissions)
- `404`: Resource not found
- `409`: Conflict (e.g., business already in collection)
- `422`: Unprocessable entity (validation failed)

## Usage Examples

### Creating a Collection
```javascript
fetch('/api/v1/collections', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer your_token_here',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'Weekend Hangout Spots',
    description: 'Places perfect for weekend relaxation',
    is_public: true
  })
})
```

### Adding a Business to Collection
```javascript
fetch('/api/v1/collections/1/businesses', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer your_token_here',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    business_id: 123,
    notes: 'Amazing coffee and cozy atmosphere',
    sort_order: 1
  })
})
```

### Following a Collection
```javascript
fetch('/api/v1/collections/1/follow', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer your_token_here'
  }
})
```

## Database Schema

### user_collections
- `id`: Primary key
- `user_id`: Foreign key to users table
- `name`: Collection name
- `description`: Collection description
- `is_public`: Whether collection is public
- `cover_image`: Collection cover image URL
- `slug`: URL-friendly slug
- `created_at`, `updated_at`: Timestamps

### collection_items
- `id`: Primary key
- `collection_id`: Foreign key to user_collections
- `business_id`: Foreign key to businesses
- `notes`: User's personal notes about the business
- `sort_order`: Custom ordering within collection
- `added_at`: When business was added
- `created_at`, `updated_at`: Timestamps

### collection_followers
- `id`: Primary key
- `collection_id`: Foreign key to user_collections
- `user_id`: Foreign key to users
- `followed_at`: When user started following
- `created_at`, `updated_at`: Timestamps

## Features Summary

1. **Personal Collections**: Users can create private or public collections
2. **Business Management**: Easy adding/removing of businesses with notes
3. **Social Features**: Follow other users' public collections
4. **Discovery**: Search and browse popular collections
5. **Organization**: Custom sorting and notes for each business
6. **Privacy**: Control whether collections are public or private
7. **Analytics**: Track follower counts and business counts

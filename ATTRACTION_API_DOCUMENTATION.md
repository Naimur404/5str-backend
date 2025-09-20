# Attraction APIs - Frontend Integration Guide

This document provides comprehensive examples of request payloads and response formats for the attraction feature APIs.

## Authentication Required

All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {your-token-here}
```

## 1. Attraction Details API

### Endpoint
```
GET /api/v1/attractions/{id}
```

### Request
No request body required. Just pass the attraction ID in the URL.

### Response Format
```json
{
  "success": true,
  "message": "Attraction retrieved successfully",
  "data": {
    "id": 3,
    "name": "Cox's Bazar Beach",
    "slug": "coxs-bazar-beach",
    "description": "World's longest natural sandy sea beach with stunning sunset views and water activities. Perfect for relaxation and beach sports.",
    "type": "attraction",
    "category": "Beach",
    "subcategory": "Natural Beach",
    "location": {
      "latitude": "21.42720000",
      "longitude": "92.00580000",
      "address": "Cox's Bazar, Chittagong Division, Bangladesh",
      "city": "Cox's Bazar",
      "area": "Cox's Bazar Sadar",
      "district": "Cox's Bazar",
      "country": "Bangladesh"
    },
    "pricing": {
      "is_free": true,
      "entry_fee": "0.00",
      "currency": "BDT"
    },
    "schedule": {
      "opening_hours": {
        "monday": {"open": "06:00", "close": "20:00"},
        "tuesday": {"open": "06:00", "close": "20:00"},
        "wednesday": {"open": "06:00", "close": "20:00"},
        "thursday": {"open": "06:00", "close": "20:00"},
        "friday": {"open": "06:00", "close": "20:00"},
        "saturday": {"open": "06:00", "close": "20:00"},
        "sunday": {"open": "06:00", "close": "20:00"}
      }
    },
    "contact": {
      "phone": "+880-341-62058",
      "email": "info@coxsbazartourism.gov.bd",
      "website": "https://coxsbazar.gov.bd"
    },
    "visit_info": {
      "facilities": ["parking", "restrooms", "restaurants", "hotels", "water_sports", "lifeguard"],
      "best_time_to_visit": {
        "months": ["November", "December", "January", "February", "March"]
      },
      "estimated_duration_minutes": 480,
      "difficulty_level": "easy"
    },
    "accessibility": {
      "wheelchair_accessible": false,
      "parking_available": true
    },
    "ratings": {
      "overall_rating": "4.38",
      "total_reviews": 5
    },
    "engagement": {
      "total_likes": 10,
      "total_dislikes": 0,
      "total_shares": 0,
      "total_views": 15001
    },
    "media": {
      "cover_image_url": "https://picsum.photos/800/600?random=31",
      "gallery_count": 3,
      "gallery": [
        {
          "id": 1,
          "image_url": "https://picsum.photos/800/600?random=31",
          "title": "Main View",
          "description": "Main view of Cox's Bazar Beach",
          "is_cover": true,
          "sort_order": 1,
          "full_image_url": "https://picsum.photos/800/600?random=31",
          "thumbnail_url": "https://picsum.photos/800/600?random=31"
        }
      ]
    },
    "status_flags": {
      "is_verified": true,
      "is_featured": true,
      "is_active": true,
      "status": "active"
    },
    "discovery_score": "95.50",
    "google_maps_url": "https://www.google.com/maps/search/Cox's+Bazar+Beach/@21.42720000,92.00580000,15z",
    "meta_data": {
      "tags": ["beach", "sunset", "swimming", "surfing", "photography", "nature"]
    },
    "reviews": [
      {
        "id": 33,
        "user": {
          "id": 1,
          "name": "Abdullah Pouros",
          "profile_image": null,
          "trust_level": 1
        },
        "rating": "4.5",
        "title": "Great beach experience!",
        "comment": "Cox's Bazar Beach is absolutely stunning...",
        "visit_date": "2024-01-15T00:00:00.000000Z",
        "experience_tags": ["sunset", "family-friendly", "photography"],
        "helpful_votes": 1,
        "total_votes": 2,
        "helpful_percentage": 50,
        "is_verified": false,
        "is_featured": false,
        "time_ago": "19 minutes ago",
        "is_recent": true
      }
    ],
    "user_interactions": {
      "user_has_liked": false,
      "user_has_bookmarked": false,
      "user_has_visited": true
    }
  }
}
```

## 2. Review Submission API

### Endpoint
```
POST /api/v1/attraction-reviews/{attractionId}/reviews
```

### Request Payload
```json
{
  "rating": 4.8,
  "title": "Spectacular coastal beauty!",
  "comment": "Cox's Bazar exceeded all expectations! The vast stretch of golden sand, crystal clear waters, and breathtaking sunsets created unforgettable memories. Perfect for both adventure seekers and those looking to relax. The local seafood was delicious too!",
  "visit_date": "2024-11-20",
  "experience_tags": ["stunning-views", "seafood", "adventure", "peaceful"],
  "visit_info": {
    "duration_hours": 8,
    "companions": 2,
    "transportation": "bus",
    "weather": "sunny",
    "crowd_level": "moderate"
  },
  "is_anonymous": false
}
```

### Field Descriptions
- `rating` (required): Float between 0.5 and 5.0
- `title` (optional): String, max 255 characters
- `comment` (required): String, min 10, max 2000 characters
- `visit_date` (optional): Date in YYYY-MM-DD format, must be today or earlier
- `experience_tags` (optional): Array of strings, max 50 characters each
- `visit_info` (optional): Object with visit-related information
- `is_anonymous` (optional): Boolean, default false

### Response Format
```json
{
  "success": true,
  "message": "Review submitted successfully",
  "data": {
    "id": 34,
    "attraction_id": "3",
    "user_id": 2,
    "rating": "4.8",
    "title": "Spectacular coastal beauty!",
    "comment": "Cox's Bazar exceeded all expectations! The vast stretch of golden sand...",
    "visit_date": "2024-11-20T00:00:00.000000Z",
    "experience_tags": ["stunning-views", "seafood", "adventure", "peaceful"],
    "visit_info": {
      "duration_hours": 8,
      "companions": 2,
      "transportation": "bus"
    },
    "is_anonymous": false,
    "status": "active",
    "helpful_votes": 0,
    "total_votes": 0,
    "helpful_percentage": 0,
    "time_ago": "0 seconds ago",
    "is_recent": true,
    "is_verified": false,
    "is_featured": false,
    "created_at": "2025-09-20T14:06:25.000000Z",
    "updated_at": "2025-09-20T14:06:25.000000Z",
    "user": {
      "id": 2,
      "name": "Mr. Nathanial Pollich MD",
      "email": "dallin59@example.net",
      "profile_image": null,
      "total_points": 100,
      "trust_level": 1
    },
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "slug": "coxs-bazar-beach",
      "overall_rating": "4.38",
      "total_reviews": 5
    }
  }
}
```

### Error Response Examples

#### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "rating": ["The rating field is required."],
    "comment": ["The comment must be at least 10 characters."]
  }
}
```

#### Duplicate Review (409)
```json
{
  "success": false,
  "message": "You have already reviewed this attraction. You can update your existing review instead."
}
```

#### Authentication Required (401)
```json
{
  "message": "Unauthenticated."
}
```

## 3. Review Upvote API

### Endpoint
```
POST /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}/helpful
```

### Request Payload
No request body required.

### Response Format
```json
{
  "success": true,
  "message": "Review marked as helpful",
  "data": {
    "helpful_votes": 2,
    "total_votes": 10,
    "helpful_percentage": 20
  }
}
```

### Error Response Examples

#### Own Review Vote (400)
```json
{
  "success": false,
  "message": "You cannot vote on your own review"
}
```

#### Already Voted (409)
```json
{
  "success": false,
  "message": "You have already voted on this review"
}
```

## 4. Review Downvote API

### Endpoint
```
POST /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}/not-helpful
```

### Request Payload
No request body required.

### Response Format
```json
{
  "success": true,
  "message": "Review marked as not helpful",
  "data": {
    "helpful_votes": 11,
    "total_votes": 9,
    "helpful_percentage": 122.2
  }
}
```

### Error Responses
Same as upvote API.

## 5. Get Reviews for Attraction

### Endpoint
```
GET /api/v1/attraction-reviews/{attractionId}/reviews
```

### Query Parameters
- `page` (optional): Page number for pagination (default: 1)
- `per_page` (optional): Items per page, max 50 (default: 10)
- `sort_by` (optional): `newest`, `oldest`, `rating_high`, `rating_low`, `helpful` (default: `helpful`)
- `rating` (optional): Filter by specific rating (1-5)
- `min_rating` (optional): Filter by minimum rating
- `featured` (optional): Show only featured reviews (`true`/`false`)
- `verified` (optional): Show only verified reviews (`true`/`false`)
- `experience_tag` (optional): Filter by experience tag

### Response Format
```json
{
  "success": true,
  "message": "Reviews retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 33,
        "attraction_id": 3,
        "user": {
          "id": 1,
          "name": "Abdullah Pouros",
          "profile_image": null,
          "total_points": 95,
          "trust_level": 1
        },
        "rating": "4.5",
        "title": "Great beach experience!",
        "comment": "Cox's Bazar Beach is absolutely stunning...",
        "visit_date": "2024-01-15T00:00:00.000000Z",
        "experience_tags": ["sunset", "family-friendly", "photography"],
        "visit_info": [],
        "helpful_votes": 1,
        "total_votes": 2,
        "helpful_percentage": 50,
        "is_verified": false,
        "is_featured": false,
        "is_anonymous": false,
        "time_ago": "19 minutes ago",
        "is_recent": true,
        "user_vote": null
      }
    ],
    "first_page_url": "http://localhost:8000/api/v1/attraction-reviews/3/reviews?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/v1/attraction-reviews/3/reviews?page=1",
    "links": [
      {"url": null, "label": "&laquo; Previous", "active": false},
      {"url": "http://localhost:8000/api/v1/attraction-reviews/3/reviews?page=1", "label": "1", "active": true},
      {"url": null, "label": "Next &raquo;", "active": false}
    ],
    "next_page_url": null,
    "path": "http://localhost:8000/api/v1/attraction-reviews/3/reviews",
    "per_page": 10,
    "prev_page_url": null,
    "to": 5,
    "total": 5
  },
  "meta": {
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "overall_rating": 4.38,
      "total_reviews": 5
    }
  }
}
```

## 6. Review Statistics API

### Endpoint
```
GET /api/v1/attraction-reviews/{attractionId}/statistics
```

### Response Format
```json
{
  "success": true,
  "message": "Review statistics retrieved successfully",
  "data": {
    "overall": {
      "total_reviews": 5,
      "overall_rating": 4.38,
      "rating_distribution": {
        "1": 0,
        "2": 0,
        "3": 1,
        "4": 2,
        "5": 2
      }
    },
    "experience_tags": {
      "family-friendly": 3,
      "sunset": 2,
      "photography": 2,
      "peaceful": 1,
      "adventure": 1
    },
    "monthly_trends": [
      {
        "year": 2025,
        "month": 9,
        "count": 2,
        "avg_rating": 4.65
      }
    ]
  }
}
```

## Frontend Integration Tips

### 1. Error Handling
Always check the `success` field in responses:
```javascript
const response = await fetch('/api/v1/attractions/3', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const data = await response.json();

if (data.success) {
  // Handle successful response
  console.log(data.data);
} else {
  // Handle error
  console.error(data.message);
}
```

### 2. Authentication
Store and use the Bearer token for protected endpoints:
```javascript
const token = localStorage.getItem('auth_token');
const headers = {
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};

if (token) {
  headers['Authorization'] = `Bearer ${token}`;
}
```

### 3. Review Submission
```javascript
const submitReview = async (attractionId, reviewData) => {
  try {
    const response = await fetch(`/api/v1/attraction-reviews/${attractionId}/reviews`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(reviewData)
    });

    const result = await response.json();
    
    if (result.success) {
      // Review submitted successfully
      return result.data;
    } else {
      // Handle validation errors
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('Review submission failed:', error);
    throw error;
  }
};
```

### 4. Vote on Review
```javascript
const voteOnReview = async (attractionId, reviewId, voteType) => {
  const endpoint = voteType === 'upvote' 
    ? `/api/v1/attraction-reviews/${attractionId}/reviews/${reviewId}/helpful`
    : `/api/v1/attraction-reviews/${attractionId}/reviews/${reviewId}/not-helpful`;

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });

    const result = await response.json();
    
    if (result.success) {
      return result.data; // Contains updated vote counts
    } else {
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('Voting failed:', error);
    throw error;
  }
};
```
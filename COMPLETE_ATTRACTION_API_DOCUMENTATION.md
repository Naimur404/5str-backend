# Complete Attraction APIs - Request Payloads and Response Formats

This document provides comprehensive request payloads and response formats for all attraction-related APIs, including details, reviews, and interactions.

## Authentication
All endpoints require Bearer token authentication:
```
Authorization: Bearer {your-token-here}
```

## Table of Contents
1. [Attraction Details API](#attraction-details-api)
2. [Attraction Review APIs](#attraction-review-apis)
3. [Attraction Interaction APIs](#attraction-interaction-apis)

---

## 1. Attraction Details API

### Get Attraction Details
**Endpoint:** `GET /api/v1/attractions/{id}`

**Response Format:**
```json
{
  "success": true,
  "message": "Attraction retrieved successfully",
  "data": {
    "id": 3,
    "name": "Cox's Bazar Beach",
    "slug": "coxs-bazar-beach",
    "description": "World's longest natural sandy sea beach...",
    "type": "Beach",
    "category": "Beach",
    "subcategory": "Natural Beach",
    "location": {
      "latitude": 21.4272,
      "longitude": 92.0058,
      "address": "Cox's Bazar, Bangladesh",
      "city": "Cox's Bazar",
      "area": "Beach Area",
      "district": "Cox's Bazar",
      "country": "Bangladesh"
    },
    "pricing": {
      "is_free": true,
      "entry_fee": 0,
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
      "phone": "+880-341-62091",
      "email": "info@coxsbazar.com",
      "website": "https://coxsbazar.gov.bd/"
    },
    "visit_info": {
      "facilities": [
        "Parking",
        "Restrooms",
        "Food Court",
        "Beach Chairs",
        "Water Sports"
      ],
      "best_time_to_visit": {
        "season": "Winter",
        "months": ["November", "December", "January", "February", "March"],
        "weather": "Pleasant and dry",
        "temperature_range": "18-28°C"
      },
      "estimated_duration_minutes": 480,
      "difficulty_level": "Easy"
    },
    "accessibility": {
      "wheelchair_accessible": false,
      "parking_available": true,
      "public_transport": true
    },
    "ratings": {
      "overall_rating": 4.38,
      "total_reviews": 156
    },
    "engagement": {
      "total_likes": 11,
      "total_dislikes": 0,
      "total_shares": 0,
      "total_views": 1250
    },
    "media": {
      "cover_image_url": "https://picsum.photos/800/600?random=31",
      "gallery_count": 5,
      "gallery": [
        {
          "id": 1,
          "image_url": "https://picsum.photos/800/600?random=31",
          "title": "Main View",
          "description": "Beautiful sunset view",
          "is_cover": true,
          "sort_order": 1,
          "full_image_url": "https://picsum.photos/1200/800?random=31",
          "thumbnail_url": "https://picsum.photos/300/200?random=31"
        }
      ]
    },
    "status_flags": {
      "is_verified": true,
      "is_featured": false,
      "is_active": true,
      "status": "active"
    },
    "discovery_score": 8.5,
    "google_maps_url": "https://maps.google.com/?q=21.4272,92.0058",
    "meta_data": {
      "seo_title": "Cox's Bazar Beach - World's Longest Natural Beach",
      "seo_description": "Visit the world's longest natural sandy beach...",
      "tags": ["beach", "natural", "sunset", "tourism"]
    },
    "reviews": [
      {
        "id": 45,
        "user": {
          "id": 2,
          "name": "John Doe",
          "profile_image": null,
          "trust_level": 2
        },
        "rating": 5.0,
        "title": "Amazing Experience!",
        "comment": "One of the most beautiful beaches I've ever visited...",
        "visit_date": "2024-08-15",
        "experience_tags": ["scenic", "relaxing", "photography"],
        "visit_info": {
          "visit_duration": "Half Day",
          "companions": "Family",
          "transportation": "Car"
        },
        "helpful_votes": 12,
        "total_votes": 15,
        "helpful_percentage": 80.0,
        "is_verified": true,
        "is_featured": false,
        "is_anonymous": false,
        "time_ago": "1 month ago",
        "is_recent": true,
        "created_at": "2024-08-16T10:30:00.000000Z"
      }
    ],
    "user_interactions": ["like", "bookmark"],
    "user_has_liked": true,
    "user_has_bookmarked": true,
    "user_has_visited": false,
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-09-21T08:20:00.000000Z"
  }
}
```

---

## 2. Attraction Review APIs

### Submit Review
**Endpoint:** `POST /api/v1/attraction-reviews`

**Request Payload:**
```json
{
  "attraction_id": 3,
  "rating": 4.5,
  "title": "Great place to visit!",
  "comment": "Had an amazing time here. The beach is clean and the sunset views are spectacular. Highly recommend for families!",
  "visit_date": "2024-09-15",
  "experience_tags": ["scenic", "family-friendly", "photography"],
  "visit_info": {
    "visit_duration": "Half Day",
    "companions": "Family",
    "transportation": "Car",
    "best_time": "Evening"
  },
  "is_anonymous": false,
  "is_public": true
}
```

**Response Format:**
```json
{
  "success": true,
  "message": "Review submitted successfully",
  "data": {
    "review": {
      "id": 156,
      "user_id": 2,
      "attraction_id": 3,
      "rating": 4.5,
      "title": "Great place to visit!",
      "comment": "Had an amazing time here...",
      "visit_date": "2024-09-15",
      "experience_tags": ["scenic", "family-friendly", "photography"],
      "visit_info": {
        "visit_duration": "Half Day",
        "companions": "Family",
        "transportation": "Car",
        "best_time": "Evening"
      },
      "helpful_votes": 0,
      "total_votes": 0,
      "is_verified": false,
      "is_featured": false,
      "is_anonymous": false,
      "is_public": true,
      "status": "active",
      "created_at": "2024-09-21T10:45:00.000000Z",
      "updated_at": "2024-09-21T10:45:00.000000Z"
    },
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "overall_rating": 4.39,
      "total_reviews": 157
    }
  }
}
```

### Upvote/Downvote Review
**Endpoint:** `POST /api/v1/attraction-reviews/{reviewId}/vote`

**Request Payload:**
```json
{
  "vote_type": "upvote"
}
```

**Response Format:**
```json
{
  "success": true,
  "message": "Vote recorded successfully",
  "data": {
    "vote": {
      "id": 15,
      "user_id": 2,
      "review_id": 45,
      "vote_type": "upvote",
      "created_at": "2024-09-21T10:50:00.000000Z"
    },
    "review_stats": {
      "helpful_votes": 13,
      "total_votes": 16,
      "helpful_percentage": 81.25
    }
  }
}
```

---

## 3. Attraction Interaction APIs

### Store New Interaction
**Endpoint:** `POST /api/v1/attraction-interactions`

#### Like Interaction
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

#### Visit Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "visit",
  "visit_date": "2024-09-15",
  "duration_minutes": 300,
  "companions": ["family", "friend"],
  "weather": "sunny",
  "rating": 4.5,
  "notes": "Had a wonderful time with family!",
  "is_public": true
}
```

#### Share Interaction
```json
{
  "attraction_id": 3,
  "interaction_type": "share",
  "platform": "facebook",
  "message": "Check out this amazing beach!",
  "is_public": true
}
```

**Response Format:**
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
      "total_likes": 12,
      "total_shares": 0
    }
  }
}
```

### Toggle Interaction (Like/Unlike)
**Endpoint:** `POST /api/v1/attraction-interactions/toggle`

**Request Payload:**
```json
{
  "attraction_id": 3,
  "interaction_type": "like",
  "notes": "Changed my mind about this place",
  "is_public": true
}
```

**Response Format (Toggle On):**
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

### Remove Interaction
**Endpoint:** `DELETE /api/v1/attraction-interactions/remove`

**Request Payload:**
```json
{
  "attraction_id": 3,
  "interaction_type": "bookmark"
}
```

**Response Format:**
```json
{
  "success": true,
  "message": "Interaction removed successfully"
}
```

### Get User's Liked Attractions
**Endpoint:** `GET /api/v1/attraction-interactions/liked?per_page=10&page=1`

**Response Format:**
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
    "per_page": 10,
    "total": 1
  }
}
```

### Get User's Bookmarked Attractions
**Endpoint:** `GET /api/v1/attraction-interactions/bookmarked`

**Response Format:** Same structure as liked attractions, but filtered for bookmarks.

### Get User's Visited Attractions  
**Endpoint:** `GET /api/v1/attraction-interactions/visited`

**Response Format:** Same structure as liked attractions, but filtered for visits.

### Get User Interactions
**Endpoint:** `GET /api/v1/attraction-interactions/user/{userId}?interaction_type=like`

**Response Format:**
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
        "attraction": {
          "id": 3,
          "name": "Cox's Bazar Beach",
          "slug": "coxs-bazar-beach",
          "cover_image_url": "https://picsum.photos/800/600?random=31",
          "overall_rating": "4.38",
          "category": "Beach",
          "city": "Cox's Bazar"
        }
      }
    ],
    "total": 1
  }
}
```

### Get Attraction Interactions
**Endpoint:** `GET /api/v1/attraction-interactions/attraction/{attractionId}`

**Response Format:**
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
        "user": {
          "id": 2,
          "name": "John Doe",
          "profile_image": null,
          "trust_level": 1
        }
      }
    ],
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

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "attraction_id": ["The attraction id field is required."],
    "rating": ["The rating must be between 0 and 5."]
  }
}
```

### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Attraction not found",
  "error": "No query results for model [App\\Models\\Attraction] 999"
}
```

## Frontend Integration Examples

### React/JavaScript Examples

#### Get Attraction Details with Proper JSON Parsing
```javascript
const getAttractionDetails = async (attractionId) => {
  try {
    const response = await fetch(`/api/v1/attractions/${attractionId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });

    const result = await response.json();
    
    if (result.success) {
      const attraction = result.data;
      
      // Now opening_hours, contact, facilities, and best_time_to_visit 
      // are properly parsed JSON objects, not escaped strings
      console.log(attraction.schedule.opening_hours.monday); // {open: "06:00", close: "20:00"}
      console.log(attraction.contact.phone); // "+880-341-62091"
      console.log(attraction.visit_info.facilities); // ["Parking", "Restrooms", ...]
      
      return attraction;
    }
  } catch (error) {
    console.error('Failed to get attraction details:', error);
    throw error;
  }
};
```

#### Submit Review
```javascript
const submitReview = async (reviewData) => {
  try {
    const response = await fetch('/api/v1/attraction-reviews', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(reviewData)
    });

    const result = await response.json();
    return result.data;
  } catch (error) {
    console.error('Review submission failed:', error);
    throw error;
  }
};
```

#### Like/Unlike Attraction
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
    return {
      isLiked: result.data.is_liked,
      totalLikes: result.data.attraction_stats.total_likes
    };
  } catch (error) {
    console.error('Like toggle failed:', error);
    throw error;
  }
};
```

This documentation provides all the necessary request payloads and response formats for your frontend integration. The AttractionController now properly parses JSON fields, so you'll receive properly formatted objects instead of escaped JSON strings.
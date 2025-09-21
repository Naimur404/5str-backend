# Attraction Review API - Instructions & Payload/Response Formats

## Overview
Complete API documentation for managing attraction reviews with voting system (upvote/downvote functionality).

---

## 1. Get Reviews for Attraction

**Endpoint:** `GET /api/v1/attraction-reviews/{attractionId}/reviews`

### Instructions:
- Retrieve all reviews for a specific attraction
- Includes user vote status (upvote/downvote) for authenticated users  
- Supports filtering by rating, featured status, verification, and experience tags
- Supports pagination and multiple sorting options

### Query Parameters:
- `rating` (optional): Filter by specific rating (0.5-5.0)
- `min_rating` (optional): Filter by minimum rating
- `featured` (optional): true to show only featured reviews
- `verified` (optional): true to show only verified reviews
- `experience_tag` (optional): Filter by specific experience tag
- `sort_by` (optional): newest, oldest, rating_high, rating_low, helpful (default)
- `per_page` (optional): Items per page (max: 50, default: 10)
- `page` (optional): Page number

### Example Request:
```
GET /api/v1/attraction-reviews/3/reviews?sort_by=helpful&per_page=15&rating=5
```

### Response Format (Authenticated User):
```json
{
  "success": true,
  "message": "Reviews retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 45,
        "attraction_id": 3,
        "user_id": 12,
        "rating": "4.5",
        "title": "Amazing experience!",
        "comment": "This place exceeded all my expectations. The view was breathtaking and the facilities were excellent.",
        "visit_date": "2025-08-15T00:00:00.000000Z",
        "experience_tags": ["scenic", "family-friendly", "peaceful"],
        "visit_info": {
          "weather": "sunny",
          "crowd_level": "moderate",
          "best_time": "morning"
        },
        "is_verified": true,
        "is_featured": false,
        "is_anonymous": false,
        "helpful_votes": 15,
        "total_votes": 18,
        "helpful_percentage": 83.3,
        "time_ago": "2 months ago",
        "is_recent": true,
        "created_at": "2025-08-16T10:30:00.000000Z",
        "user": {
          "id": 12,
          "name": "John Doe",
          "profile_image": "https://example.com/profiles/12.jpg"
        },
        "user_vote_status": {
          "has_voted": true,
          "is_upvoted": true,
          "is_downvoted": false,
          "vote_details": {
            "id": 234,
            "review_id": 45,
            "user_id": 8,
            "is_helpful": true,
            "created_at": "2025-09-20T14:20:00.000000Z"
          }
        }
      }
    ],
    "per_page": 15,
    "total": 48,
    "current_page": 1,
    "last_page": 4
  },
  "meta": {
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "overall_rating": "4.38",
      "total_reviews": 48
    }
  }
}
```

### Response Format (Unauthenticated User):
```json
{
  "success": true,
  "message": "Reviews retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 45,
        "attraction_id": 3,
        "user_id": 12,
        "rating": "4.5",
        "title": "Amazing experience!",
        "comment": "This place exceeded all my expectations...",
        "visit_date": "2025-08-15T00:00:00.000000Z",
        "experience_tags": ["scenic", "family-friendly", "peaceful"],
        "helpful_votes": 15,
        "total_votes": 18,
        "helpful_percentage": 83.3,
        "time_ago": "2 months ago",
        "user": {
          "id": 12,
          "name": "John Doe",
          "profile_image": "https://example.com/profiles/12.jpg"
        }
      }
    ]
  }
}
```

---

## 2. Submit New Review

**Endpoint:** `POST /api/v1/attraction-reviews/{attractionId}/reviews`

### Instructions:
- Submit a new review for an attraction (authenticated users only)
- User can only submit one review per attraction
- Rating is required (0.5-5.0), comment minimum 10 characters
- Optional fields: title, visit_date, experience_tags, visit_info

### Request Payload:
```json
{
  "rating": 4.5,
  "title": "Amazing place to visit!",
  "comment": "This attraction was absolutely wonderful. The scenery is breathtaking and the facilities are well-maintained. Highly recommended for families.",
  "visit_date": "2025-08-15",
  "experience_tags": ["scenic", "family-friendly", "peaceful", "well-maintained"],
  "visit_info": {
    "weather": "sunny",
    "crowd_level": "moderate",
    "best_time": "morning",
    "duration_hours": 3,
    "companions": "family"
  },
  "is_anonymous": false
}
```

### Response Format:
```json
{
  "success": true,
  "message": "Review submitted successfully",
  "data": {
    "id": 46,
    "attraction_id": 3,
    "user_id": 8,
    "rating": "4.5",
    "title": "Amazing place to visit!",
    "comment": "This attraction was absolutely wonderful...",
    "visit_date": "2025-08-15T00:00:00.000000Z",
    "experience_tags": ["scenic", "family-friendly", "peaceful", "well-maintained"],
    "visit_info": {
      "weather": "sunny",
      "crowd_level": "moderate",
      "best_time": "morning",
      "duration_hours": 3,
      "companions": "family"
    },
    "is_verified": false,
    "is_featured": false,
    "is_anonymous": false,
    "helpful_votes": 0,
    "total_votes": 0,
    "helpful_percentage": 0,
    "status": "active",
    "created_at": "2025-09-21T15:30:00.000000Z",
    "user": {
      "id": 8,
      "name": "Jane Smith",
      "profile_image": null
    }
  }
}
```

### Error Response (Duplicate Review):
```json
{
  "success": false,
  "message": "You have already reviewed this attraction. You can update your existing review instead."
}
```

---

## 3. Get Single Review

**Endpoint:** `GET /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}`

### Instructions:
- Retrieve details of a specific review
- Includes user vote status if authenticated
- Shows full review details with user and attraction information

### Response Format:
```json
{
  "success": true,
  "message": "Review retrieved successfully",
  "data": {
    "id": 45,
    "attraction_id": 3,
    "user_id": 12,
    "rating": "4.5",
    "title": "Amazing experience!",
    "comment": "This place exceeded all my expectations...",
    "visit_date": "2025-08-15T00:00:00.000000Z",
    "experience_tags": ["scenic", "family-friendly"],
    "helpful_votes": 15,
    "total_votes": 18,
    "helpful_percentage": 83.3,
    "created_at": "2025-08-16T10:30:00.000000Z",
    "user": {
      "id": 12,
      "name": "John Doe",
      "profile_image": "https://example.com/profiles/12.jpg"
    },
    "attraction": {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "slug": "coxs-bazar-beach"
    },
    "user_vote_status": {
      "has_voted": false,
      "is_upvoted": false,
      "is_downvoted": false,
      "vote_details": null
    }
  }
}
```

---

## 4. Update Review

**Endpoint:** `PUT /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}`

### Instructions:
- Update user's own review (authenticated users only)
- Can update any field except attraction_id
- User can only update their own reviews

### Request Payload:
```json
{
  "rating": 5.0,
  "title": "Updated: Absolutely fantastic!",
  "comment": "After visiting again, I must say this place is even better than I initially thought...",
  "experience_tags": ["scenic", "family-friendly", "peaceful", "must-visit"]
}
```

### Response Format:
```json
{
  "success": true,
  "message": "Review updated successfully",
  "data": {
    "id": 46,
    "attraction_id": 3,
    "user_id": 8,
    "rating": "5.0",
    "title": "Updated: Absolutely fantastic!",
    "comment": "After visiting again, I must say this place is even better...",
    "updated_at": "2025-09-21T16:45:00.000000Z"
  }
}
```

---

## 5. Delete Review

**Endpoint:** `DELETE /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}`

### Instructions:
- Delete user's own review (authenticated users only)
- Permanently removes the review and all associated votes
- User can only delete their own reviews

### Response Format:
```json
{
  "success": true,
  "message": "Review deleted successfully"
}
```

---

## 6. Upvote Review (Mark as Helpful)

**Endpoint:** `POST /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}/helpful`

### Instructions:
- Mark a review as helpful (upvote)
- User cannot vote on their own review
- User can only vote once per review
- Returns updated vote counts and user's vote status

### Response Format:
```json
{
  "success": true,
  "message": "Review marked as helpful",
  "data": {
    "helpful_votes": 16,
    "total_votes": 19,
    "helpful_percentage": 84.2,
    "user_vote_status": {
      "has_voted": true,
      "is_upvoted": true,
      "is_downvoted": false
    }
  }
}
```

### Error Response (Own Review):
```json
{
  "success": false,
  "message": "You cannot vote on your own review"
}
```

### Error Response (Already Voted):
```json
{
  "success": false,
  "message": "You have already voted on this review"
}
```

---

## 7. Downvote Review (Mark as Not Helpful)

**Endpoint:** `POST /api/v1/attraction-reviews/{attractionId}/reviews/{reviewId}/not-helpful`

### Instructions:
- Mark a review as not helpful (downvote)
- User cannot vote on their own review
- User can only vote once per review
- Returns updated vote counts and user's vote status

### Response Format:
```json
{
  "success": true,
  "message": "Review marked as not helpful",
  "data": {
    "helpful_votes": 15,
    "total_votes": 19,
    "helpful_percentage": 78.9,
    "user_vote_status": {
      "has_voted": true,
      "is_upvoted": false,
      "is_downvoted": true
    }
  }
}
```

---

## 8. Get Review Statistics

**Endpoint:** `GET /api/v1/attraction-reviews/{attractionId}/statistics`

### Instructions:
- Get comprehensive review statistics for an attraction
- Public endpoint, no authentication required
- Includes rating distribution, experience tags, and monthly trends

### Response Format:
```json
{
  "success": true,
  "message": "Review statistics retrieved successfully",
  "data": {
    "overall": {
      "total_reviews": 48,
      "overall_rating": "4.38",
      "rating_distribution": {
        "1": 2,
        "2": 3,
        "3": 8,
        "4": 15,
        "5": 20
      }
    },
    "experience_tags": {
      "scenic": 28,
      "family-friendly": 22,
      "peaceful": 18,
      "well-maintained": 15,
      "crowded": 12,
      "expensive": 8,
      "must-visit": 25
    },
    "monthly_trends": [
      {
        "year": 2025,
        "month": 9,
        "count": 8,
        "avg_rating": "4.25"
      },
      {
        "year": 2025,
        "month": 8,
        "count": 12,
        "avg_rating": "4.42"
      }
    ]
  }
}
```

---

## User Vote Status Explanation

### Fields:
- `has_voted`: Boolean indicating if the current user has voted on this review
- `is_upvoted`: Boolean indicating if the user marked the review as helpful (upvote)
- `is_downvoted`: Boolean indicating if the user marked the review as not helpful (downvote)
- `vote_details`: Full vote record with timestamp (null if not voted)

### Usage Examples:

**Show upvote button state:**
```javascript
const showUpvoteActive = review.user_vote_status?.is_upvoted || false;
const showDownvoteActive = review.user_vote_status?.is_downvoted || false;
```

**Handle vote button clicks:**
```javascript
// For upvote button
if (!user_vote_status.has_voted) {
  // Show as inactive, allow upvote
} else if (user_vote_status.is_upvoted) {
  // Show as active/selected
} else {
  // Show as inactive (user downvoted)
}
```

---

## Field Descriptions

### Review Fields:
- `rating` - Decimal rating from 0.5 to 5.0
- `title` - Optional review title (max 255 characters)
- `comment` - Required review text (min 10, max 2000 characters)
- `visit_date` - Date of visit (cannot be future date)
- `experience_tags` - Array of experience descriptors
- `visit_info` - Object with visit details (weather, crowd, etc.)
- `is_anonymous` - Boolean for anonymous reviews
- `helpful_votes` - Count of helpful votes (upvotes)
- `total_votes` - Total vote count (upvotes + downvotes)

### Validation Rules:
- `rating`: Required, numeric, 0.5-5.0
- `comment`: Required, string, 10-2000 characters
- `title`: Optional, string, max 255 characters
- `visit_date`: Optional, date, must be today or earlier
- `experience_tags`: Optional, array of strings, max 50 chars each

---

## Common Error Responses

### Validation Error (422):
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
  "message": "Review not found"
}
```

### Forbidden (403):
```json
{
  "success": false,
  "message": "You can only edit your own reviews"
}
```

---

## Usage Instructions

1. **Authentication**: Include Bearer token for protected endpoints
2. **Content Type**: Use `application/json` for POST/PUT requests
3. **Vote Status**: Check `user_vote_status` flags to show correct UI state
4. **Error Handling**: Always check `success` field in responses
5. **Rate Limiting**: Implement appropriate rate limiting for vote endpoints
6. **Caching**: Cache review statistics and update on new reviews
7. **UI Updates**: Update vote counts immediately after successful votes
8. **Validation**: Validate rating and comment length client-side
9. **Anonymous Reviews**: Handle `is_anonymous` flag for privacy
10. **Experience Tags**: Suggest common tags based on attraction type
# Featured & Popular Attractions API - Instructions & Payload/Response Formats

## Overview
Complete API documentation for featured and popular attractions endpoints with request parameters and response formats.

---

## 1. Get Featured Attractions

**Endpoint:** `GET /api/v1/attractions/featured`

### Instructions:
- Retrieve attractions that have been manually marked as featured by administrators
- Returns attractions with highest discovery scores first
- Supports location-based filtering with GPS coordinates
- Can filter by city or area names
- Includes user interaction status for authenticated users
- Public endpoint, no authentication required (optional authentication for interaction data)

### Query Parameters:
- `limit` (optional): Number of attractions to return (max: 20, default: 10)
- `latitude` (optional): User's current latitude (-90 to 90)
- `longitude` (optional): User's current longitude (-180 to 180)  
- `radius` (optional): Search radius in kilometers (default: 25km for featured)
- `city` (optional): Filter by city name (partial match)
- `area` (optional): Filter by area name (partial match)

### Example Requests:
```
GET /api/v1/attractions/featured?latitude=23.8103&longitude=90.4125&radius=30
GET /api/v1/attractions/featured?latitude=21.4272&longitude=92.0058&radius=15&limit=5
```

### Response Format (Authenticated User):
```json
{
  "success": true,
  "message": "Featured attractions retrieved successfully",
  "data": [
    {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "slug": "coxs-bazar-beach",
      "description": "World's longest natural sandy sea beach located in Cox's Bazar, Bangladesh. Perfect for beach lovers and sunset watching.",
      "type": "beach",
      "category": "Natural Wonder",
      "subcategory": "Beach",
      "latitude": "21.4272",
      "longitude": "92.0058",
      "address": "Cox's Bazar Beach Road, Cox's Bazar",
      "city": "Cox's Bazar",
      "area": "Beach Area",
      "district": "Cox's Bazar",
      "country": "Bangladesh",
      "is_free": true,
      "entry_fee": "0.00",
      "currency": "BDT",
      "opening_hours": {
        "monday": {"open": "06:00", "close": "22:00"},
        "tuesday": {"open": "06:00", "close": "22:00"},
        "wednesday": {"open": "06:00", "close": "22:00"},
        "thursday": {"open": "06:00", "close": "22:00"},
        "friday": {"open": "06:00", "close": "22:00"},
        "saturday": {"open": "06:00", "close": "22:00"},
        "sunday": {"open": "06:00", "close": "22:00"}
      },
      "contact_info": {
        "phone": "+880-341-62387",
        "email": "info@coxsbazar.gov.bd",
        "website": "https://coxsbazar.gov.bd"
      },
      "facilities": ["parking", "restaurants", "toilets", "shops", "lifeguard"],
      "best_time_to_visit": {
        "months": ["November", "December", "January", "February", "March"],
        "time_of_day": ["sunrise", "sunset"],
        "weather": ["clear", "sunny"]
      },
      "estimated_duration_minutes": 180,
      "difficulty_level": "easy",
      "accessibility_info": {
        "wheelchair_accessible": false,
        "parking_available": true,
        "public_transport": true
      },
      "overall_rating": "4.38",
      "total_reviews": 127,
      "total_likes": 3421,
      "total_dislikes": 45,
      "total_shares": 892,
      "total_views": 15678,
      "discovery_score": "92.50",
      "is_verified": true,
      "is_featured": true,
      "is_active": true,
      "status": "active",
      "google_maps_url": "https://www.google.com/maps/search/Cox's%20Bazar%20Beach/@21.4272,92.0058,15z",
      "cover_image_url": "https://picsum.photos/800/600?random=31",
      "gallery_count": 8,
      "distance": "2.45",
      "gallery": [
        {
          "id": 45,
          "image_url": "https://picsum.photos/800/600?random=31",
          "title": "Sunrise at Cox's Bazar",
          "description": "Beautiful sunrise view from the beach",
          "is_cover": true,
          "sort_order": 1,
          "full_image_url": "https://picsum.photos/1200/900?random=31",
          "thumbnail_url": "https://picsum.photos/300/200?random=31"
        }
      ],
      "created_at": "2025-06-15T10:30:00.000000Z",
      "updated_at": "2025-09-20T14:25:00.000000Z",
      "user_interactions": ["like", "bookmark"],
      "user_has_liked": true,
      "user_has_bookmarked": true
    },
    {
      "id": 7,
      "name": "Sundarbans Mangrove Forest",
      "slug": "sundarbans-mangrove-forest",
      "description": "World's largest mangrove forest and UNESCO World Heritage Site, home to the Bengal tiger.",
      "type": "forest",
      "category": "Natural Wonder",
      "subcategory": "Wildlife Sanctuary",
      "latitude": "22.4707",
      "longitude": "89.5370",
      "is_free": false,
      "entry_fee": "500.00",
      "currency": "BDT",
      "overall_rating": "4.65",
      "total_reviews": 89,
      "total_likes": 2847,
      "total_dislikes": 12,
      "total_shares": 654,
      "total_views": 12456,
      "discovery_score": "89.75",
      "is_featured": true,
      "distance": "18.67",
      "cover_image_url": "https://picsum.photos/800/600?random=37",
      "user_interactions": [],
      "user_has_liked": false,
      "user_has_bookmarked": false
    }
  ],
  "meta": {
    "total_count": 2,
    "search_location": {
      "latitude": 23.8103,
      "longitude": 90.4125,
      "radius_km": 25
    }
  }
}
```

### Response Format (Unauthenticated User):
```json
{
  "success": true,
  "message": "Featured attractions retrieved successfully",
  "data": [
    {
      "id": 3,
      "name": "Cox's Bazar Beach",
      "slug": "coxs-bazar-beach",
      "description": "World's longest natural sandy sea beach...",
      "type": "beach",
      "category": "Natural Wonder",
      "overall_rating": "4.38",
      "total_reviews": 127,
      "total_likes": 3421,
      "is_featured": true,
      "cover_image_url": "https://picsum.photos/800/600?random=31"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

---

## 2. Get Popular Attractions

**Endpoint:** `GET /api/v1/attractions/popular`

### Instructions:
- Retrieve attractions sorted by popularity metrics (rating, likes, views, reviews)
- Filters attractions with minimum rating of 3.5 and at least 1 review
- Supports location-based filtering with GPS coordinates and radius
- Can filter by city or area names
- Supports different sorting methods and timeframe filters
- Location-aware combined scoring balances popularity with distance
- Includes user interaction status for authenticated users

### Query Parameters:
- `limit` (optional): Number of attractions to return (max: 20, default: 10)
- `timeframe` (optional): Recent popularity timeframe in days (default: 30, 'all' for all-time)
- `sort_by` (optional): Sorting method - combined (default), rating, likes, views, reviews, distance
- `latitude` (optional): User's current latitude (-90 to 90)
- `longitude` (optional): User's current longitude (-180 to 180)  
- `radius` (optional): Search radius in kilometers (default: 50km for popular)
- `city` (optional): Filter by city name (partial match)
- `area` (optional): Filter by area name (partial match)

### Example Requests:
```

GET /api/v1/attractions/popular?latitude=23.8103&longitude=90.4125&radius=25&sort_by=distance
```

### Response Format:
```json
{
  "success": true,
  "message": "Popular attractions retrieved successfully",
  "data": [
    {
      "id": 12,
      "name": "Ahsan Manzil Museum",
      "slug": "ahsan-manzil-museum",
      "description": "Historical palace turned museum showcasing Bangladesh's rich cultural heritage and Nawab history.",
      "type": "museum",
      "category": "Historical Site",
      "subcategory": "Museum",
      "latitude": "23.7085",
      "longitude": "90.4055",
      "city": "Dhaka",
      "area": "Old Dhaka",
      "is_free": false,
      "entry_fee": "20.00",
      "currency": "BDT",
      "overall_rating": "4.52",
      "total_reviews": 234,
      "total_likes": 5678,
      "total_dislikes": 23,
      "total_shares": 1234,
      "total_views": 23456,
      "discovery_score": "88.25",
      "is_verified": true,
      "is_featured": false,
      "distance": "3.25",
      "cover_image_url": "https://picsum.photos/800/600?random=42",
      "gallery_count": 12,
      "user_interactions": ["like"],
      "user_has_liked": true,
      "user_has_bookmarked": false
    },
    {
      "id": 15,
      "name": "Lalbagh Fort",
      "slug": "lalbagh-fort",
      "description": "17th-century Mughal fort complex with beautiful architecture and historical significance.",
      "type": "historical_site",
      "category": "Historical Site",
      "subcategory": "Fort",
      "latitude": "23.7197",
      "longitude": "90.3861",
      "city": "Dhaka",
      "area": "Lalbagh",
      "overall_rating": "4.41",
      "total_reviews": 189,
      "total_likes": 4321,
      "total_views": 19876,
      "discovery_score": "85.50",
      "is_featured": true,
      "distance": "5.12",
      "cover_image_url": "https://picsum.photos/800/600?random=45",
      "user_interactions": [],
      "user_has_liked": false,
      "user_has_bookmarked": false
    }
  ],
  "meta": {
    "total_count": 2,
    "timeframe_days": "30",
    "sorted_by": "combined",
    "filters_applied": {
      "min_rating": 3.5,
      "min_reviews": 1
    },
    "search_location": {
      "latitude": 23.8103,
      "longitude": 90.4125,
      "radius_km": 50
    },
    "location_filters": {
      "city": "Dhaka"
    }
  }
}
```

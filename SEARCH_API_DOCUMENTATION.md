# Universal Search API Documentation

## Overview
The Universal Search API provides comprehensive search functionality for both businesses and business offerings from any page of the application. Users can search from the home page or any other page with unified results.

## Base URL
```
/api/v1/search
```

## Endpoints

### 1. Universal Search
**GET** `/api/v1/search`

Search for businesses and/or offerings with comprehensive filtering and sorting options.

#### Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| q | string | No | - | Search term (business name, description, address, area, city) |
| type | string | No | 'all' | Search type: 'all', 'businesses', 'offerings' |
| latitude | float | No | - | User's latitude for location-based search |
| longitude | float | No | - | User's longitude for location-based search |
| radius | integer | No | 20 | Search radius in kilometers |
| category_id | integer | No | - | Filter by category ID |
| page | integer | No | 1 | Page number for pagination |
| limit | integer | No | 20 | Number of results per page |
| sort | string | No | 'relevance' | Sort order: 'relevance', 'rating', 'distance', 'name', 'popular', 'newest' |

#### Business-specific filters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| min_rating | float | - | Minimum rating filter |
| is_verified | boolean | - | Show only verified businesses |
| has_delivery | boolean | - | Show only businesses with delivery |
| has_pickup | boolean | - | Show only businesses with pickup |

#### Offering-specific filters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| offering_type | string | - | Filter by offering type |
| price_min | float | - | Minimum price filter |
| price_max | float | - | Maximum price filter |
| is_popular | boolean | - | Show only popular offerings |
| is_featured | boolean | - | Show only featured offerings |

#### Example Request
```bash
curl "http://localhost:8000/api/v1/search?q=restaurant&type=all&latitude=23.7465&longitude=90.3754&sort=rating&limit=10" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "search_term": "restaurant",
    "search_type": "all",
    "total_results": 1,
    "results": {
      "businesses": {
        "data": [
          {
            "id": 2,
            "business_name": "Star Kabab & Restaurant",
            "slug": "star-kabab-restaurant",
            "description": "Traditional Bengali and Chinese cuisine...",
            "area": "Dhanmondi",
            "city": "Dhaka",
            "overall_rating": "3.50",
            "total_reviews": 11,
            "is_verified": true,
            "category": {
              "id": 1,
              "name": "Restaurants",
              "slug": "restaurants"
            },
            "type": "business"
          }
        ],
        "pagination": {
          "current_page": 1,
          "last_page": 1,
          "per_page": 20,
          "total": 1,
          "has_more": false
        }
      },
      "offerings": {
        "data": [],
        "pagination": {...}
      }
    },
    "suggestions": [
      {
        "suggestion": "Star Kabab & Restaurant",
        "type": "business",
        "id": 2
      }
    ],
    "filters_applied": {
      "category_id": null,
      "location": {
        "latitude": "23.7465",
        "longitude": "90.3754",
        "radius_km": 20
      },
      "sort": "rating"
    }
  }
}
```

### 2. Search Suggestions (Autocomplete)
**GET** `/api/v1/search/suggestions`

Get search suggestions for autocomplete functionality.

#### Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| q | string | Yes | - | Search term (minimum 2 characters) |
| category_id | integer | No | - | Filter suggestions by category |
| limit | integer | No | 10 | Maximum number of suggestions |

#### Example Request
```bash
curl "http://localhost:8000/api/v1/search/suggestions?q=res&limit=5" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "suggestion": "Star Kabab & Restaurant",
      "type": "business",
      "id": 2
    },
    {
      "suggestion": "Restaurants",
      "type": "category",
      "id": 1
    }
  ]
}
```

### 3. Popular Searches
**GET** `/api/v1/search/popular`

Get popular search terms based on recent search activity.

#### Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 20 | Maximum number of popular searches |
| category_id | integer | No | - | Filter by category |

#### Example Request
```bash
curl "http://localhost:8000/api/v1/search/popular?limit=10" \
  -H "Accept: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "search_term": "training center",
      "search_count": 5
    },
    {
      "search_term": "restaurant",
      "search_count": 3
    }
  ]
}
```

## Search Types

### All (type=all)
Returns both businesses and offerings in a single response with separate pagination for each type.

### Businesses Only (type=businesses)
Returns only business results with business-specific filters and sorting options.

### Offerings Only (type=offerings)
Returns only business offering results (products/services) with offering-specific filters.

## Sorting Options

- **relevance**: Default relevance-based sorting (exact matches first)
- **rating**: Sort by rating (highest first)
- **distance**: Sort by distance from user location (requires lat/lng)
- **name**: Sort alphabetically by name
- **popular**: Sort by popularity (most reviews first)
- **newest**: Sort by creation date (newest first)

## Location-Based Search

When latitude and longitude are provided:
- Results are filtered within the specified radius
- Distance information is included in the response
- Distance sorting becomes available

## Analytics Integration

All search queries are automatically logged for analytics purposes including:
- Search terms and filters used
- Number of results returned
- User location (if provided)
- Click tracking when users select results

## Error Handling

The API returns standard HTTP status codes:
- **200**: Success
- **422**: Validation error (missing required parameters)
- **500**: Internal server error

Error responses include:
```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

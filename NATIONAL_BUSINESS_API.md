# National Business API Documentation

## Overview
The National Business API provides filtered access to businesses that operate nationwide in Bangladesh, with advanced tag-based filtering to show accurate categorization.

## Endpoints

### 1. Get National Businesses
**GET** `/api/v1/businesses/national`

Retrieves paginated list of national businesses with various filtering options.

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page` | integer | Page number (default: 1) | `?page=2` |
| `limit` | integer | Results per page (default: 20) | `?limit=10` |
| `category_id` | integer | Filter by category ID | `?category_id=63` |
| `business_model` | string | Filter by business model | `?business_model=manufacturing` |
| `product_tag` | string | Filter by single product tag | `?product_tag=ice cream` |
| `product_tags` | string/array | Filter by multiple product tags | `?product_tags=ice cream,dairy` |
| `business_tag` | string | Filter by business tag | `?business_tag=premium` |
| `item_type` | string | Filter by predefined item types | `?item_type=ice_cream` |
| `min_rating` | float | Minimum rating filter | `?min_rating=4.0` |
| `sort` | string | Sort order | `?sort=rating` |

#### Item Types
- `ice_cream` - Ice Cream & Dairy products
- `biscuits_snacks` - Biscuits, cookies, and snacks  
- `beverages` - All types of beverages
- `food_processing` - Food processing and manufacturing

#### Sort Options
- `rating` - Highest rated first (default)
- `popular` - Most reviewed first
- `name` - Alphabetical order
- `featured` - Featured businesses first

#### Example Requests

```bash
# Get all national businesses
GET /api/v1/businesses/national

# Get ice cream brands only
GET /api/v1/businesses/national?item_type=ice_cream

# Get businesses with specific product tags
GET /api/v1/businesses/national?product_tags=biscuit,cookie,snack

# Get manufacturing businesses with high rating
GET /api/v1/businesses/national?business_model=manufacturing&min_rating=4.0

# Get beverages sorted by popularity
GET /api/v1/businesses/national?item_type=beverages&sort=popular
```

#### Response Format

```json
{
  "success": true,
  "data": {
    "businesses": [
      {
        "id": 1,
        "business_name": "Polar Ice Cream",
        "slug": "polar-ice-cream",
        "description": "Leading ice cream manufacturer...",
        "overall_rating": 4.5,
        "total_reviews": 245,
        "is_national": true,
        "service_coverage": "national",
        "business_model": "manufacturing",
        "product_tags": ["ice cream", "dairy", "frozen dessert", "premium"],
        "business_tags": ["family-owned", "premium", "traditional"],
        "category": {
          "id": 63,
          "name": "Dairy & Ice Cream"
        },
        "free_maps": {
          "openstreetmap_url": "https://...",
          "leaflet_data": {...},
          "mapbox_url": "https://..."
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 20,
      "total": 45,
      "has_more": true
    },
    "available_filters": {
      "item_types": {
        "ice_cream": "Ice Cream & Dairy",
        "biscuits_snacks": "Biscuits & Snacks",
        "beverages": "Beverages",
        "food_processing": "Food Processing"
      },
      "business_models": ["manufacturing", "brand", "delivery_only", "online_service"],
      "sort_options": ["rating", "popular", "name", "featured"]
    }
  }
}
```

### 2. Get National Business Filters
**GET** `/api/v1/businesses/national/filters`

Retrieves available filter options and statistics for national businesses.

#### Response Format

```json
{
  "success": true,
  "data": {
    "item_types": {
      "ice_cream": {
        "label": "Ice Cream & Dairy",
        "count": 5,
        "tags": ["ice cream", "dairy", "frozen dessert", "gelato", "kulfi"]
      },
      "biscuits_snacks": {
        "label": "Biscuits & Snacks", 
        "count": 8,
        "tags": ["biscuit", "cookie", "snack", "chips", "crackers", "wafer"]
      },
      "beverages": {
        "label": "Beverages",
        "count": 12,
        "tags": ["beverage", "soft drink", "juice", "water", "tea", "coffee"]
      },
      "food_processing": {
        "label": "Food Processing",
        "count": 6,
        "tags": ["food processing", "manufacturing", "packaged food", "ready meals"]
      }
    },
    "available_product_tags": [
      "ice cream", "dairy", "biscuit", "beverage", "snack", ...
    ],
    "available_business_tags": [
      "premium", "family-owned", "organic", "halal", ...
    ],
    "business_models": {
      "manufacturing": 15,
      "brand": 8,
      "delivery_only": 3
    },
    "sort_options": {
      "rating": "Highest Rated",
      "popular": "Most Popular", 
      "name": "Alphabetical",
      "featured": "Featured First"
    },
    "total_national_businesses": 31
  }
}
```

## Key Features

### 🎯 **Accurate Tag-Based Filtering**
- **Problem Solved**: Previously "Pran Foods" appeared in ice cream section despite not being an ice cream brand
- **Solution**: Dynamic filtering based on actual product tags ensures accurate categorization
- **Example**: Ice cream section now only shows businesses tagged with ice cream-related tags

### 🏷️ **Flexible Tagging System**
- **Product Tags**: What the business sells (`ice cream`, `biscuit`, `beverage`)
- **Business Tags**: Business characteristics (`premium`, `organic`, `family-owned`)
- **Multiple Tag Support**: Search for businesses matching any of several tags

### 📊 **Smart Filtering Options**
- **Item Type Filters**: Predefined categories for common searches
- **Custom Tag Filters**: Filter by specific product or business tags
- **Business Model Filters**: Manufacturing, brand, delivery-only, etc.
- **Rating Filters**: Minimum rating requirements

### 🔍 **Enhanced Search Experience**
- **Filter Discovery**: `/national/filters` endpoint shows available options
- **Count Information**: See how many businesses match each filter
- **Combined Filters**: Mix and match multiple filter types
- **Intelligent Sorting**: Multiple sort options with meaningful defaults

## Use Cases

1. **Category Pages**: Show all ice cream brands, beverage companies, etc.
2. **Search Filtering**: Allow users to refine national business searches
3. **Admin Management**: Understand business distribution across categories
4. **Analytics**: Track popular business types and tags

## Integration Notes

- All endpoints are **public** (no authentication required)
- Responses include **free map alternatives** for businesses with coordinates
- **Pagination** is built-in for performance
- **Error handling** provides meaningful error messages
- **Backward compatible** with existing API structure
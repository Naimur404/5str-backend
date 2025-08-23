# 5SRT Business Discovery Platform

<p align="center">
    <img src="https://img.shields.io/badge/Laravel-11.x-red?style=for-the-badge&logo=laravel" alt="Laravel">
    <img src="https://img.shields.io/badge/PHP-8.2%2B-blue?style=for-the-badge&logo=php" alt="PHP">
    <img src="https://img.shields.io/badge/Filament-3.x-orange?style=for-the-badge" alt="Filament">
    <img src="https://img.shields.io/badge/MySQL-8.0-blue?style=for-the-badge&logo=mysql" alt="MySQL">
</p>

<p align="center">
    A comprehensive business discovery platform that connects users with local businesses, services, and offerings. Built with Laravel and featuring a modern admin panel powered by Filament.
</p>

## 🚀 Features

### 🏢 Business Management
- **Business Registration & Profiles**: Complete business information management
- **Category-based Organization**: Hierarchical categorization system
- **Location-based Services**: Geographic discovery with radius-based search
- **Business Verification**: Admin-controlled verification system
- **Multi-media Support**: Image galleries, logos, and cover photos

### 🔍 Advanced Search & Discovery
- **Universal Search API**: Search businesses and offerings simultaneously
- **Intelligent Autocomplete**: Real-time search suggestions
- **Location-based Filtering**: GPS-powered nearby business discovery
- **Advanced Filtering**: Rating, price, features, and availability filters
- **Popular Searches**: Trending search terms analytics

### 📊 Analytics & Insights
- **Trending Data**: Business and category trending analysis
- **Search Analytics**: Comprehensive search behavior tracking
- **View Tracking**: Business and offering view analytics
- **Performance Metrics**: Discovery scores and engagement tracking

### 👥 User Management
- **Role-based Access Control**: Super-admin, admin, moderator, business-owner, user roles
- **Approval Workflows**: Business owner changes require admin approval
- **User Authentication**: Sanctum-powered API authentication
- **Profile Management**: Complete user profile system
- **Smart Notifications**: Role-based notification system with admin controls

### 🛠 Admin Panel (Filament)
- **Business Management**: Full CRUD operations with role-based access
- **User Administration**: User management with role assignments
- **Analytics Dashboard**: Comprehensive analytics and reporting
- **Content Moderation**: Review and approval systems
- **Notification Center**: Database notifications with real-time polling
- **Profile Management**: Complete admin profile system

### 📱 Mobile-Friendly API
- **RESTful API**: Clean, optimized API responses
- **Location Services**: GPS integration for mobile apps
- **Optimized Responses**: Minimal data transfer for mobile performance
- **Authentication**: Token-based authentication with Sanctum

## 🛠 Technology Stack

- **Backend Framework**: Laravel 11.x
- **Admin Panel**: Filament 3.x with database notifications
- **Authentication**: Laravel Sanctum
- **Permissions**: Spatie Laravel Permission
- **Database**: MySQL 8.0+
- **PHP Version**: 8.2+

## 📋 Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Node.js & NPM (for assets compilation)

## 🚀 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Naimur404/5str-backend.git
   cd 5str-backend
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your `.env` file**
   ```env
   APP_NAME="5SRT Business Discovery"
   APP_URL=http://localhost:8000
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=5srt_backend
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Database setup**
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Install frontend dependencies and compile assets**
   ```bash
   npm install
   npm run build
   ```

7. **Start the development server**
   ```bash
   php artisan serve
   ```

## 🗄 Database Seeding

The project includes comprehensive seeders for development and testing:

```bash
# Full database reset with sample data
php artisan migrate:fresh --seed

# Generate trending data
php artisan analytics:generate-test-data --count=50

# Create test notifications
php artisan notification:test --title="Test Notification" --message="Testing the notification system"
```

### Default Admin Account
- **Email**: admin@5srt.com
- **Password**: password

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication
The API uses Laravel Sanctum for authentication. Include the token in the Authorization header:
```
Authorization: Bearer {your-token}
```

## 🔐 Authentication Endpoints

### Register User
**POST** `/api/v1/register`

**Request Payload:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "city": "Dhaka",
    "current_latitude": 23.7465,
    "current_longitude": 90.3754
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 123,
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890",
            "city": "Dhaka",
            "current_latitude": "23.7465",
            "current_longitude": "90.3754",
            "email_verified_at": null,
            "created_at": "2025-08-23T10:30:00.000000Z",
            "updated_at": "2025-08-23T10:30:00.000000Z"
        },
        "token": "1|eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

### Login User
**POST** `/api/v1/login`

**Request Payload:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 123,
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890",
            "city": "Dhaka",
            "current_latitude": "23.7465",
            "current_longitude": "90.3754",
            "roles": ["user"]
        },
        "token": "2|eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

### Get Authenticated User
**GET** `/api/v1/auth/user`

**Headers:**
```
Authorization: Bearer {your-token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 123,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+1234567890",
        "city": "Dhaka",
        "current_latitude": "23.7465",
        "current_longitude": "90.3754",
        "roles": ["user"],
        "created_at": "2025-08-23T10:30:00.000000Z"
    }
}
```

### Logout
**POST** `/api/v1/auth/logout`

**Headers:**
```
Authorization: Bearer {your-token}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

## 🏠 Home & Discovery Endpoints

### Get Home Screen Data
**GET** `/api/v1/home`

**Query Parameters:**
- `latitude` (optional): User's latitude
- `longitude` (optional): User's longitude  
- `radius` (optional): Search radius in km (default: 10)

**Example Request:**
```bash
curl "http://localhost:8000/api/v1/home?latitude=23.7465&longitude=90.3754&radius=15"
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "banners": [
            {
                "id": 1,
                "title": "Welcome to 5SRT",
                "description": "Discover local businesses",
                "image_url": "https://example.com/banner.jpg",
                "link": "https://example.com",
                "is_active": true,
                "sort_order": 1
            }
        ],
        "top_services": [
            {
                "id": 1,
                "name": "Restaurants",
                "slug": "restaurants",
                "icon_image": "https://example.com/icon.jpg",
                "color_code": "#FF5722",
                "business_count": 45
            }
        ],
        "popular_nearby": [
            {
                "id": 2,
                "business_name": "Star Kabab & Restaurant",
                "slug": "star-kabab-restaurant",
                "description": "Traditional Bengali and Chinese cuisine",
                "area": "Dhanmondi",
                "city": "Dhaka",
                "overall_rating": "3.50",
                "total_reviews": 11,
                "is_verified": true,
                "distance_km": "2.5",
                "category": {
                    "id": 1,
                    "name": "Restaurants",
                    "slug": "restaurants"
                }
            }
        ],
        "featured_offerings": [
            {
                "id": 1,
                "offering_name": "Chicken Biryani",
                "offering_type": "menu_item",
                "price": "350.00",
                "is_popular": true,
                "business": {
                    "id": 2,
                    "business_name": "Star Kabab & Restaurant",
                    "area": "Dhanmondi"
                }
            }
        ]
    }
}
```

### Get Trending Data
**GET** `/api/v1/trending`

**Query Parameters:**
- `type` (optional): 'businesses' or 'categories' (default: 'all')
- `limit` (optional): Number of results (default: 10)

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "trending_businesses": [
            {
                "id": 2,
                "business_name": "Star Kabab & Restaurant",
                "trend_score": 85.5,
                "category": "Restaurants",
                "total_views": 245,
                "recent_searches": 32
            }
        ],
        "trending_categories": [
            {
                "id": 1,
                "name": "Restaurants",
                "trend_score": 92.3,
                "business_count": 45,
                "recent_searches": 128
            }
        ]
    }
}
```

## 🔍 Search Endpoints

### Universal Search
**GET** `/api/v1/search`

**Query Parameters:**
- `q` (optional): Search term
- `type` (optional): 'all', 'businesses', 'offerings' (default: 'all')
- `latitude` (optional): User's latitude
- `longitude` (optional): User's longitude
- `radius` (optional): Search radius in km (default: 20)
- `category_id` (optional): Filter by category ID
- `page` (optional): Page number (default: 1)
- `limit` (optional): Results per page (default: 20)
- `sort` (optional): 'relevance', 'rating', 'distance', 'name', 'popular', 'newest'
- `min_rating` (optional): Minimum rating filter
- `is_verified` (optional): Show only verified businesses

**Example Request:**
```bash
curl "http://localhost:8000/api/v1/search?q=restaurant&type=all&latitude=23.7465&longitude=90.3754&sort=rating&limit=10"
```

**Success Response (200):**
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
                        "description": "Traditional Bengali and Chinese cuisine",
                        "area": "Dhanmondi",
                        "city": "Dhaka",
                        "overall_rating": "3.50",
                        "total_reviews": 11,
                        "is_verified": true,
                        "distance_km": "2.5",
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
                "pagination": {
                    "current_page": 1,
                    "last_page": 1,
                    "per_page": 20,
                    "total": 0,
                    "has_more": false
                }
            }
        }
    }
}
```

### Search Suggestions
**GET** `/api/v1/search/suggestions`

**Query Parameters:**
- `q` (required): Search term (minimum 2 characters)
- `category_id` (optional): Filter by category
- `limit` (optional): Max suggestions (default: 10)

**Success Response (200):**
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

## 🏢 Business Endpoints

### Get Business List
**GET** `/api/v1/businesses`

**Query Parameters:**
- `latitude`, `longitude`, `radius`: Location-based filtering
- `category_id`: Filter by category
- `min_rating`: Minimum rating filter
- `is_verified`: Show only verified businesses
- `page`, `limit`: Pagination
- `sort`: Sorting option

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "businesses": [
            {
                "id": 2,
                "business_name": "Star Kabab & Restaurant",
                "slug": "star-kabab-restaurant",
                "description": "Traditional Bengali and Chinese cuisine",
                "area": "Dhanmondi",
                "city": "Dhaka",
                "overall_rating": "3.50",
                "total_reviews": 11,
                "is_verified": true,
                "category": {
                    "id": 1,
                    "name": "Restaurants"
                },
                "logo_image": {
                    "id": 1,
                    "image_url": "https://example.com/logo.jpg"
                }
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 20,
            "total": 1
        }
    }
}
```

### Get Business Details
**GET** `/api/v1/businesses/{id}`

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "business_name": "Star Kabab & Restaurant",
        "slug": "star-kabab-restaurant",
        "description": "Traditional Bengali and Chinese cuisine",
        "business_email": "star@example.com",
        "business_phone": "+8801234567890",
        "website": "https://starrestaurant.com",
        "address": "123 Main Street",
        "area": "Dhanmondi",
        "city": "Dhaka",
        "postal_code": "1205",
        "latitude": "23.7465",
        "longitude": "90.3754",
        "opening_hours": {
            "monday": "09:00-22:00",
            "tuesday": "09:00-22:00"
        },
        "business_features": ["delivery", "pickup", "dine_in"],
        "overall_rating": "3.50",
        "total_reviews": 11,
        "is_verified": true,
        "is_featured": false,
        "category": {
            "id": 1,
            "name": "Restaurants",
            "slug": "restaurants"
        },
        "owner": {
            "id": 3,
            "name": "Restaurant Owner"
        },
        "images": [
            {
                "id": 1,
                "image_url": "https://example.com/image1.jpg",
                "is_logo": true
            }
        ],
        "offerings_count": 8,
        "offers_count": 2
    }
}
```

### Get Business Offerings
**GET** `/api/v1/businesses/{id}/offerings`

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "offerings": [
            {
                "id": 1,
                "offering_name": "Chicken Biryani",
                "description": "Aromatic basmati rice with tender chicken",
                "offering_type": "menu_item",
                "price": "350.00",
                "currency": "BDT",
                "is_popular": true,
                "is_featured": false,
                "variants": [
                    {
                        "id": 1,
                        "variant_name": "Regular",
                        "price": "350.00"
                    },
                    {
                        "id": 2,
                        "variant_name": "Large",
                        "price": "450.00"
                    }
                ]
            }
        ],
        "pagination": {
            "current_page": 1,
            "total": 8
        }
    }
}
```

## 📂 Category Endpoints

### Get Categories
**GET** `/api/v1/categories`

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Restaurants",
            "slug": "restaurants",
            "description": "Food and dining establishments",
            "icon_image": "https://example.com/icon.jpg",
            "color_code": "#FF5722",
            "level": 1,
            "is_featured": true,
            "business_count": 45,
            "subcategories": [
                {
                    "id": 2,
                    "name": "Fast Food",
                    "slug": "fast-food",
                    "parent_id": 1
                }
            ]
        }
    ]
}
```

## 👤 User Management Endpoints (Protected)

### Get User Favorites
**GET** `/api/v1/user/favorites`

**Headers:**
```
Authorization: Bearer {your-token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "favoritable_type": "business",
            "favoritable_id": 2,
            "business": {
                "id": 2,
                "business_name": "Star Kabab & Restaurant",
                "area": "Dhanmondi",
                "overall_rating": "3.50"
            },
            "created_at": "2025-08-23T10:30:00.000000Z"
        }
    ]
}
```

### Add to Favorites
**POST** `/api/v1/user/favorites`

**Request Payload:**
```json
{
    "favoritable_type": "business",
    "favoritable_id": 2
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Added to favorites successfully",
    "data": {
        "id": 1,
        "favoritable_type": "business",
        "favoritable_id": 2,
        "created_at": "2025-08-23T10:30:00.000000Z"
    }
}
```

## ❌ Error Responses

All endpoints return consistent error responses:

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password field is required."]
    }
}
```

**Authentication Error (401):**
```json
{
    "success": false,
    "message": "Unauthorized"
}
```

**Not Found Error (404):**
```json
{
    "success": false,
    "message": "Resource not found"
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Internal server error",
    "error": "Detailed error message (only in debug mode)"
}
```

## 🎛 Admin Panel

Access the admin panel at: `http://localhost:8000/admin`

### Features
- **Dashboard**: Analytics overview and key metrics
- **Business Management**: CRUD operations with approval workflows
- **User Management**: Role-based user administration
- **Content Moderation**: Review and approval systems
- **Notification Center**: Real-time database notifications (30s polling)
- **Profile Management**: Complete admin profile system
- **Analytics**: Comprehensive reporting and insights

### Role-based Access
- **Super Admin**: Full system access
- **Admin**: Business and user management + notifications
- **Moderator**: Content moderation and analytics
- **Business Owner**: Own business management only

### Notification System
- Smart role-based notifications
- Admin notifications for important events only
- Real-time polling every 30 seconds
- Configurable notification rules in `config/notifications.php`

## 🔧 Development Commands

### Testing
```bash
# Run all tests
php artisan test

# Test specific feature
php artisan test --filter=AuthenticationTest
```

### Notifications
```bash
# Test notification system
php artisan notification:test --title="Test" --message="Testing notifications"

# Test offer creation notifications
php artisan test:offer-creation

# Test notification events
php artisan test:notification-events
```

### Analytics
```bash
# Generate test analytics data
php artisan analytics:generate-test-data --count=50
```

### Code Quality
```bash
# Fix code style
./vendor/bin/pint

# Clear caches
php artisan cache:clear && php artisan config:clear
```

## 🌟 Key Features Explained

### Universal Search System
Sophisticated search that simultaneously searches businesses and offerings:
- Real-time autocomplete suggestions
- Location-based filtering with radius support
- Advanced filtering by ratings, features, price ranges
- Intelligent relevance scoring
- Analytics tracking for search behavior

### Role-based Access Control
Comprehensive permission system:
- Hierarchical role structure with Spatie Laravel Permission
- Granular permissions for each resource
- Business owner isolation (own businesses only)
- Smart notification routing based on roles

### Smart Notification System
Advanced notification system with:
- Role-based notification routing
- Configurable rules for admin notifications
- Database notifications with Filament integration
- Real-time polling in admin panel
- Selective notifications to prevent spam

### Analytics & Trending
Advanced analytics tracking:
- Business discovery patterns and user behavior
- Search analytics with trending terms
- Geographic usage patterns and location analytics
- Performance metrics and engagement tracking

### Mobile-Optimized API
Clean, efficient API for mobile applications:
- Minimal response payloads with essential data
- Location-aware services with GPS integration
- Token-based authentication with Laravel Sanctum
- Comprehensive error handling and validation

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🤝 Support

For support, email support@5srt.com or open an issue on GitHub.

---

<p align="center">
    Built with ❤️ by the 5SRT Team
</p>

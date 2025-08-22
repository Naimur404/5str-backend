# 5SRT Business Discovery Platform

<p align="center">
    <img src="https://img.shields.io/badge/Laravel-10.x-red?style=for-the-badge&logo=laravel" alt="Laravel">
    <img src="https://img.shields.io/badge/PHP-8.1%2B-blue?style=for-the-badge&logo=php" alt="PHP">
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

### 🛠 Admin Panel (Filament)
- **Business Management**: Full CRUD operations with role-based access
- **User Administration**: User management with role assignments
- **Analytics Dashboard**: Comprehensive analytics and reporting
- **Content Moderation**: Review and approval systems
- **System Configuration**: Categories, permissions, and settings

### 📱 Mobile-Friendly API
- **RESTful API**: Clean, optimized API responses
- **Location Services**: GPS integration for mobile apps
- **Optimized Responses**: Minimal data transfer for mobile performance
- **Authentication**: Token-based authentication with Sanctum

## 🛠 Technology Stack

- **Backend Framework**: Laravel 10.x
- **Admin Panel**: Filament 3.x
- **Authentication**: Laravel Sanctum
- **Permissions**: Spatie Laravel Permission
- **Database**: MySQL 8.0+
- **PHP Version**: 8.1+

## 📋 Prerequisites

- PHP 8.1 or higher
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
```

### Default Admin Account
- **Email**: admin@5srt.com
- **Password**: password

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api/v1
```

### Key Endpoints

#### Authentication
- `POST /register` - User registration
- `POST /login` - User login
- `GET /auth/user` - Get authenticated user
- `POST /auth/logout` - Logout

#### Universal Search
- `GET /search` - Universal search for businesses and offerings
- `GET /search/suggestions` - Search autocomplete suggestions
- `GET /search/popular` - Popular search terms

#### Business Discovery
- `GET /home` - Home screen data with featured content
- `GET /businesses` - Business listings with filtering
- `GET /businesses/{id}` - Business details
- `GET /categories` - Category listings
- `GET /trending` - Trending businesses and data

### Example API Calls

```bash
# Universal search
curl "http://localhost:8000/api/v1/search?q=restaurant&latitude=23.7465&longitude=90.3754"

# Home screen data
curl "http://localhost:8000/api/v1/home?latitude=23.7465&longitude=90.3754"

# Business details
curl "http://localhost:8000/api/v1/businesses/1"
```

For complete API documentation, see [SEARCH_API_DOCUMENTATION.md](./SEARCH_API_DOCUMENTATION.md)

## 🎛 Admin Panel

Access the admin panel at: `http://localhost:8000/admin`

### Features
- **Dashboard**: Analytics overview and key metrics
- **Business Management**: CRUD operations with approval workflows
- **User Management**: Role-based user administration
- **Content Moderation**: Review and approval systems
- **Analytics**: Comprehensive reporting and insights
- **System Settings**: Configuration and permissions

### Role-based Access
- **Super Admin**: Full system access
- **Admin**: Business and user management
- **Moderator**: Content moderation and analytics
- **Business Owner**: Own business management only

## 🔧 Development

### Running Tests
```bash
php artisan test
```

### Code Style
```bash
./vendor/bin/pint
```

### Generate Documentation
```bash
php artisan route:list
php artisan model:show Business
```

## 🌟 Key Features Explained

### Universal Search System
The platform features a sophisticated search system that can simultaneously search businesses and their offerings:
- Real-time autocomplete suggestions
- Location-based filtering with radius support
- Advanced filtering by ratings, features, price ranges
- Intelligent relevance scoring
- Analytics tracking for search behavior

### Role-based Access Control
Comprehensive permission system using Spatie Laravel Permission:
- Hierarchical role structure
- Granular permissions for each resource
- Business owner isolation (can only manage their own businesses)
- Approval workflows for business changes

### Analytics & Trending
Advanced analytics system tracking:
- Business discovery patterns
- Search behavior and trends
- Geographic usage patterns
- Performance metrics and engagement

### Mobile-Optimized API
Clean, efficient API designed for mobile applications:
- Minimal response payloads
- Location-aware services
- Token-based authentication
- Comprehensive error handling

## 📝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🤝 Support

For support, email support@5srt.com or open an issue on GitHub.

## 🔗 Related Projects

- [5SRT Mobile App](https://github.com/Naimur404/5srt-mobile) - React Native mobile application
- [5SRT Frontend](https://github.com/Naimur404/5srt-frontend) - Web frontend application

---

<p align="center">
    Built with ❤️ by the 5SRT Team
</p>

# Debug Commands for Attraction API Issues

## Issue: API returns success but no attractions in data array

### Potential Causes & Debug Steps:

## 1. Check if attractions exist in database
```bash
php artisan tinker
```

```php
// Check total attractions count
\App\Models\Attraction::count()

// Check active attractions
\App\Models\Attraction::active()->count()

// Check featured attractions
\App\Models\Attraction::active()->featured()->count()

// Check popular attractions (with filters)
\App\Models\Attraction::active()
    ->where('overall_rating', '>=', 3.5)
    ->where('total_reviews', '>=', 1)
    ->count()

// Check some sample data
\App\Models\Attraction::active()->take(5)->get(['id', 'name', 'is_featured', 'is_active', 'status', 'overall_rating', 'total_reviews'])
```

## 2. Check Model Scopes
```php
// Test active scope
\App\Models\Attraction::whereRaw('is_active = 1 AND status = "active"')->count()

// Test featured scope  
\App\Models\Attraction::where('is_featured', true)->count()
```

## 3. Debug SQL Queries

Add this to your controller methods temporarily to see the actual SQL:
```php
// In featured() method, add before executing query:
\DB::enableQueryLog();
$attractions = $query->orderBy('discovery_score', 'desc')->take($limit)->get();
$queries = \DB::getQueryLog();
\Log::info('Featured Query:', $queries);
```

## 4. Check Discovery Scores
```php
// Check if discovery_score is set
\App\Models\Attraction::active()->whereNull('discovery_score')->count()
\App\Models\Attraction::active()->where('discovery_score', '>', 0)->count()

// Update discovery scores
\App\Models\Attraction::active()->get()->each(function($attraction) {
    $attraction->updateDiscoveryScore();
});
```

## 5. Test Raw Queries
```php
// Direct SQL test for featured
DB::select("SELECT id, name, is_featured, is_active, status, discovery_score 
           FROM attractions 
           WHERE is_active = 1 AND status = 'active' AND is_featured = 1 
           ORDER BY discovery_score DESC 
           LIMIT 10");

// Direct SQL test for popular
DB::select("SELECT id, name, overall_rating, total_reviews, total_likes, total_views 
           FROM attractions 
           WHERE is_active = 1 AND status = 'active' 
           AND overall_rating >= 3.5 AND total_reviews >= 1 
           LIMIT 10");
```

## 6. Check Database Schema

Make sure your attractions table has the required columns:
```bash
php artisan tinker
```

```php
Schema::hasColumn('attractions', 'is_active')
Schema::hasColumn('attractions', 'is_featured')  
Schema::hasColumn('attractions', 'status')
Schema::hasColumn('attractions', 'discovery_score')
Schema::hasColumn('attractions', 'overall_rating')
Schema::hasColumn('attractions', 'total_reviews')
```

## 7. Test API with Debug Response

Temporarily add debug info to controller response:
```php
return response()->json([
    'success' => true,
    'message' => 'Featured attractions retrieved successfully',
    'data' => $attractions,
    'debug' => [
        'query_count' => $attractions->count(),
        'total_active' => Attraction::active()->count(),
        'total_featured' => Attraction::active()->featured()->count(),
        'has_city_filter' => $request->has('city'),
        'has_location_filter' => $request->has('latitude') && $request->has('longitude')
    ],
    'meta' => $meta
]);
```

## 8. Quick Fixes

### Reset discovery scores:
```php
\App\Models\Attraction::active()->get()->each(function($attraction) {
    $score = ($attraction->overall_rating * 20) + ($attraction->total_reviews * 2) + ($attraction->total_likes * 0.1);
    $attraction->update(['discovery_score' => $score]);
});
```

### Ensure some test featured attractions:
```php
\App\Models\Attraction::active()->take(5)->update(['is_featured' => true]);
```

### Create test data if none exists:
```bash
php artisan tinker
```

```php
\App\Models\Attraction::create([
    'name' => 'Test Featured Attraction',
    'slug' => 'test-featured-attraction',
    'description' => 'Test description',
    'type' => 'test',
    'category' => 'Test Category',
    'latitude' => 23.8103,
    'longitude' => 90.4125,
    'city' => 'Dhaka',
    'area' => 'Test Area',
    'is_free' => true,
    'overall_rating' => 4.5,
    'total_reviews' => 10,
    'total_likes' => 50,
    'total_views' => 100,
    'is_featured' => true,
    'is_active' => true,
    'status' => 'active',
    'discovery_score' => 85.5
]);
```

Run these commands to identify the exact issue!
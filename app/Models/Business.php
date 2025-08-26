<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'slug',
        'description',
        'category_id',
        'subcategory_id',
        'owner_user_id',
        'business_email',
        'business_phone',
        'website_url',
        'full_address',
        'latitude',
        'longitude',
        'city',
        'area',
        'landmark',
        'opening_hours',
        'price_range',
        'has_delivery',
        'has_pickup',
        'has_parking',
        'is_verified',
        'is_featured',
        'is_active',
        'overall_rating',
        'total_reviews',
        'discovery_score',
        'approval_status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'pending_changes',
        'has_pending_changes',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'opening_hours' => 'array',
        'price_range' => 'integer',
        'has_delivery' => 'boolean',
        'has_pickup' => 'boolean',
        'has_parking' => 'boolean',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'overall_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'discovery_score' => 'decimal:2',
        'pending_changes' => 'array',
        'has_pending_changes' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function images()
    {
        return $this->hasMany(BusinessImage::class);
    }

    public function logoImage()
    {
        return $this->hasOne(BusinessImage::class)->where('image_type', 'logo');
    }

    public function coverImage()
    {
        return $this->hasOne(BusinessImage::class)->where('image_type', 'cover');
    }

    public function galleryImages()
    {
        return $this->hasMany(BusinessImage::class)->where('image_type', 'gallery');
    }

    public function businessCategories()
    {
        return $this->hasMany(BusinessCategory::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'business_categories');
    }

    public function offerings()
    {
        return $this->hasMany(BusinessOffering::class);
    }

    public function products()
    {
        return $this->hasMany(BusinessOffering::class)->where('offering_type', 'product');
    }

    public function services()
    {
        return $this->hasMany(BusinessOffering::class)->where('offering_type', 'service');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function activeOffers()
    {
        return $this->hasMany(Offer::class)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_to', '>=', now());
    }

    public function searchLogs()
    {
        return $this->hasMany(SearchLog::class, 'clicked_business_id');
    }

    public function trendingData()
    {
        return $this->hasMany(TrendingData::class, 'item_id')->where('item_type', 'business');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeNearby($query, $latitude, $longitude, $radiusKm = 10)
    {
        return $query->whereRaw(
            "( 6371 * acos( cos( radians(?) ) * 
              cos( radians( latitude ) ) * 
              cos( radians( longitude ) - radians(?) ) + 
              sin( radians(?) ) * 
              sin( radians( latitude ) ) ) ) < ?", 
            [$latitude, $longitude, $latitude, $radiusKm]
        )->orderByRaw(
            "( 6371 * acos( cos( radians(?) ) * 
              cos( radians( latitude ) ) * 
              cos( radians( longitude ) - radians(?) ) + 
              sin( radians(?) ) * 
              sin( radians( latitude ) ) ) )", 
            [$latitude, $longitude, $latitude]
        );
    }

    public function scopeNearbyWithDistance($query, $latitude, $longitude, $radiusKm = 10)
    {
        return $query->selectRaw("businesses.*, 
            ( 6371 * acos( cos( radians(?) ) * 
              cos( radians( latitude ) ) * 
              cos( radians( longitude ) - radians(?) ) + 
              sin( radians(?) ) * 
              sin( radians( latitude ) ) ) ) AS distance", 
            [$latitude, $longitude, $latitude])
            ->having('distance', '<', $radiusKm)
            ->orderBy('distance');
    }

    public function scopeWithRating($query, $minRating = 0)
    {
        return $query->where('overall_rating', '>=', $minRating);
    }

    public function scopePriceRange($query, $minPrice, $maxPrice = null)
    {
        $query->where('price_range', '>=', $minPrice);
        if ($maxPrice) {
            $query->where('price_range', '<=', $maxPrice);
        }
        return $query;
    }

    /**
     * Calculate and update discovery score
     */
    public function updateDiscoveryScore($userLatitude = null, $userLongitude = null)
    {
        $score = 0;

        // Distance factor (30%) - closer is better
        if ($userLatitude && $userLongitude) {
            $distance = $this->calculateDistance($userLatitude, $userLongitude);
            $distanceScore = max(0, 100 - ($distance * 10)); // 10km = 0 points
            $score += $distanceScore * 0.30;
        }

        // Rating factor (25%)
        $ratingScore = ($this->overall_rating / 5) * 100;
        $score += $ratingScore * 0.25;

        // Recent review activity (20%)
        $recentReviews = $this->reviews()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $recentActivityScore = min(100, $recentReviews * 10);
        $score += $recentActivityScore * 0.20;

        // Active offers (15%)
        $activeOffersCount = $this->activeOffers()->count();
        $offersScore = min(100, $activeOffersCount * 25);
        $score += $offersScore * 0.15;

        // User preference match (10%) - can be enhanced based on user behavior
        $preferenceScore = 50; // Base score
        $score += $preferenceScore * 0.10;

        $this->update(['discovery_score' => round($score, 2)]);
        return $score;
    }

    /**
     * Calculate distance to a point in kilometers
     */
    public function calculateDistance($latitude, $longitude)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($latitude - $this->latitude);
        $dLon = deg2rad($longitude - $this->longitude);

        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * 
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        return $distance;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Attraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'category',
        'subcategory',
        'latitude',
        'longitude',
        'address',
        'city',
        'area',
        'district',
        'country',
        'is_free',
        'entry_fee',
        'currency',
        'opening_hours',
        'contact_info',
        'facilities',
        'best_time_to_visit',
        'estimated_duration_minutes',
        'difficulty_level',
        'accessibility_info',
        'overall_rating',
        'total_reviews',
        'total_likes',
        'total_dislikes',
        'total_shares',
        'total_views',
        'discovery_score',
        'is_verified',
        'is_featured',
        'is_active',
        'status',
        'rejection_reason',
        'created_by',
        'verified_by',
        'verified_at',
        'meta_data',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_free' => 'boolean',
        'entry_fee' => 'decimal:2',
        'opening_hours' => 'array',
        'contact_info' => 'array',
        'facilities' => 'array',
        'best_time_to_visit' => 'array',
        'estimated_duration_minutes' => 'integer',
        'accessibility_info' => 'array',
        'overall_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'total_likes' => 'integer',
        'total_dislikes' => 'integer',
        'total_shares' => 'integer',
        'total_views' => 'integer',
        'discovery_score' => 'decimal:2',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'meta_data' => 'array',
    ];

    protected $appends = [
        'google_maps_url',
        'cover_image_url',
        'gallery_count',
    ];

    /**
     * Automatically generate slug when creating
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attraction) {
            if (empty($attraction->slug)) {
                $attraction->slug = Str::slug($attraction->name);
            }
        });
    }

    /**
     * Relationships
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function galleries()
    {
        return $this->hasMany(AttractionGallery::class);
    }

    public function gallery()
    {
        return $this->galleries(); // Alias for backwards compatibility
    }

    public function coverImage()
    {
        return $this->hasOne(AttractionGallery::class)->where('is_cover', true);
    }

    public function reviews()
    {
        return $this->hasMany(AttractionReview::class);
    }

    public function interactions()
    {
        return $this->hasMany(UserAttractionInteraction::class);
    }

    public function likes()
    {
        return $this->hasMany(UserAttractionInteraction::class)->where('interaction_type', 'like');
    }

    public function dislikes()
    {
        return $this->hasMany(UserAttractionInteraction::class)->where('interaction_type', 'dislike');
    }

    public function shares()
    {
        return $this->hasMany(UserAttractionInteraction::class)->where('interaction_type', 'share');
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * Accessors
     */
    public function getGoogleMapsUrlAttribute()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        return "https://www.google.com/maps/search/" . urlencode($this->name) . "/@" . $this->latitude . "," . $this->longitude . ",15z";
    }

    public function getCoverImageUrlAttribute()
    {
        return $this->coverImage?->full_image_url ?? $this->galleries()->first()?->full_image_url;
    }

    public function getGalleryCountAttribute()
    {
        return $this->galleries()->count();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopePaid($query)
    {
        return $query->where('is_free', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeInCity($query, $city)
    {
        return $query->where('city', 'LIKE', '%' . $city . '%');
    }

    public function scopeInArea($query, $area)
    {
        return $query->where('area', 'LIKE', '%' . $area . '%');
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
        );
    }

    public function scopeNearbyWithDistance($query, $latitude, $longitude, $radiusKm = 10)
    {
        return $query->selectRaw("attractions.*, 
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

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Helper Methods
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

    public function updateRating()
    {
        $totalReviews = $this->reviews()->count();
        $averageRating = $this->reviews()->avg('rating') ?? 0;

        $this->update([
            'total_reviews' => $totalReviews,
            'overall_rating' => round($averageRating, 2)
        ]);

        return $this;
    }

    public function incrementViews()
    {
        $this->increment('total_views');
        return $this;
    }

    public function updateDiscoveryScore($userLatitude = null, $userLongitude = null)
    {
        $score = 0;

        // Distance factor (25%) - closer is better
        if ($userLatitude && $userLongitude) {
            $distance = $this->calculateDistance($userLatitude, $userLongitude);
            $distanceScore = max(0, 100 - ($distance * 5)); // 20km = 0 points
            $score += $distanceScore * 0.25;
        }

        // Rating factor (30%)
        $ratingScore = ($this->overall_rating / 5) * 100;
        $score += $ratingScore * 0.30;

        // Recent activity (25%)
        $recentReviews = $this->reviews()->where('created_at', '>=', now()->subDays(30))->count();
        $recentLikes = $this->likes()->where('created_at', '>=', now()->subDays(30))->count();
        $recentActivity = ($recentReviews * 10) + ($recentLikes * 5);
        $activityScore = min(100, $recentActivity);
        $score += $activityScore * 0.25;

        // Verification and features (20%)
        $verificationScore = 0;
        if ($this->is_verified) $verificationScore += 50;
        if ($this->is_featured) $verificationScore += 30;
        if ($this->is_free) $verificationScore += 20;
        $score += min(100, $verificationScore) * 0.20;

        $this->update(['discovery_score' => round($score, 2)]);
        return $score;
    }

    public function hasUserInteraction($userId, $type)
    {
        return $this->interactions()
            ->where('user_id', $userId)
            ->where('interaction_type', $type)
            ->exists();
    }

    public function getUserInteraction($userId, $type)
    {
        return $this->interactions()
            ->where('user_id', $userId)
            ->where('interaction_type', $type)
            ->first();
    }
}

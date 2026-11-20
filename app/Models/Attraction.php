<?php

namespace App\Models;

use App\Concerns\HasGeoLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Attraction extends Model
{
    use HasFactory;
    use HasGeoLocation;
    use Searchable;

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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        // Generated binary POINT column (MySQL/MariaDB spatial index); not JSON-serializable.
        'location',
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

    // cover_image_url and gallery_count are in $appends, so they run for
    // every serialized attraction. Use the eager-loaded gallery when it is
    // there (the list endpoints load it) instead of one query per row.

    public function getCoverImageUrlAttribute()
    {
        $gallery = $this->loadedGallery();

        return $this->coverImage?->full_image_url
            ?? ($gallery !== null ? $gallery->first() : $this->galleries()->first())?->full_image_url;
    }

    public function getGalleryCountAttribute()
    {
        return $this->loadedGallery()?->count() ?? $this->galleries()->count();
    }

    /**
     * The gallery collection if it was eager loaded under either name
     * ("gallery" is an alias of "galleries"), otherwise null.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AttractionGallery>|null
     */
    private function loadedGallery(): ?\Illuminate\Database\Eloquent\Collection
    {
        foreach (['gallery', 'galleries'] as $relation) {
            if ($this->relationLoaded($relation)) {
                return $this->getRelation($relation);
            }
        }

        return null;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /*
    |--------------------------------------------------------------------------
    | Scout / Meilisearch
    |--------------------------------------------------------------------------
    */

    public function searchableAs(): string
    {
        return 'attractions';
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) ($this->is_active ?? true) && ($this->status ?? 'active') === 'active';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'description' => (string) ($this->description ?? ''),
            'category' => (string) ($this->category ?? ''),
            'subcategory' => (string) ($this->subcategory ?? ''),
            'type' => (string) ($this->type ?? ''),
            'city' => (string) ($this->city ?? ''),
            'area' => (string) ($this->area ?? ''),
            'address' => (string) ($this->address ?? ''),
            'is_active' => (bool) ($this->is_active ?? false),
            'is_verified' => (bool) ($this->is_verified ?? false),
            'is_featured' => (bool) ($this->is_featured ?? false),
            'is_free' => (bool) ($this->is_free ?? false),
            'overall_rating' => (float) ($this->overall_rating ?? 0),
            'total_reviews' => (int) ($this->total_reviews ?? 0),
            'created_at' => optional($this->created_at)->timestamp,
            '_geo' => $this->latitude !== null && $this->longitude !== null
                ? ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude]
                : null,
        ];
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
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        $query->whereNotNull('latitude')->whereNotNull('longitude');
        self::applyNearbyFilter($query, 'location', $latitude, $longitude, (float) $radiusKm);

        return $query;
    }

    public function scopeNearbyWithDistance($query, $latitude, $longitude, $radiusKm = 10)
    {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        $query->whereNotNull('latitude')->whereNotNull('longitude');
        self::applyDistanceSelect($query, 'attractions', 'location', $latitude, $longitude, 'distance');
        self::applyNearbyFilter($query, 'location', $latitude, $longitude, (float) $radiusKm);

        return $query->orderBy('distance');
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

    /**
     * Columns calculateDiscoveryScore() reads without a user location
     * (besides id). Bulk refreshes select only these; keep in sync.
     */
    public const DISCOVERY_COLUMNS = ['overall_rating', 'is_verified', 'is_featured', 'is_free'];

    /**
     * Discovery score (0-100). Read-only: this never writes.
     *
     * The distance term only applies when a user location is given, so a
     * location-aware score belongs in a response, not in the database.
     * Pass $activity (from discoveryActivity()) to avoid per-row queries.
     *
     * @param  array{reviews: int, likes: int}|null  $activity
     */
    public function calculateDiscoveryScore($userLatitude = null, $userLongitude = null, ?array $activity = null): float
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
        $activity ??= static::discoveryActivity([$this->id])[$this->id] ?? ['reviews' => 0, 'likes' => 0];
        $recentActivity = ($activity['reviews'] * 10) + ($activity['likes'] * 5);
        $activityScore = min(100, $recentActivity);
        $score += $activityScore * 0.25;

        // Verification and features (20%)
        $verificationScore = 0;
        if ($this->is_verified) $verificationScore += 50;
        if ($this->is_featured) $verificationScore += 30;
        if ($this->is_free) $verificationScore += 20;
        $score += min(100, $verificationScore) * 0.20;

        return $score;
    }

    /**
     * Recent (30-day) review and like counts for many attractions in one
     * query, keyed by attraction id.
     *
     * @param  iterable<int>  $ids
     * @return array<int, array{reviews: int, likes: int}>
     */
    public static function discoveryActivity(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $since = now()->subDays(30);

        return static::query()
            ->whereKey($ids->all())
            ->withCount([
                'reviews as discovery_recent_reviews' => fn ($q) => $q->where('created_at', '>=', $since),
                'likes as discovery_recent_likes' => fn ($q) => $q->where('created_at', '>=', $since),
            ])
            ->get(['id'])
            ->mapWithKeys(fn ($a) => [$a->id => [
                'reviews' => (int) $a->getAttribute('discovery_recent_reviews'),
                'likes' => (int) $a->getAttribute('discovery_recent_likes'),
            ]])
            ->all();
    }

    /**
     * Persist the location-independent discovery score (no distance term),
     * so the stored value is the same for every caller. Kept fresh by the
     * discovery:refresh-scores command; GET requests must not call this.
     */
    public function updateDiscoveryScore(): float
    {
        $score = $this->calculateDiscoveryScore();
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

<?php

namespace App\Models;

use App\Concerns\AuditableByAdmin;
use App\Concerns\HasGeoLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Business extends Model
{
    use HasFactory;
    use AuditableByAdmin;
    use HasGeoLocation;
    use Searchable;

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
        'is_national',
        'service_coverage',
        'service_areas',
        'business_model',
        'product_tags',
        'business_tags',
        'overall_rating',
        'total_reviews',
        'rating_averages',
        'rating_distribution',
        'discovery_score',
        'approval_status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'pending_changes',
        'has_pending_changes',
        'parent_id',
        'embedding',
        'embedding_hash',
        'embedding_updated_at',
    ];

    protected $hidden = [
        // Generated binary POINT column (MySQL/MariaDB spatial index); not JSON-serializable.
        'location',
        'created_at',
        'updated_at',
        'embedding',
        'embedding_hash',
    ];

    protected $appends = [
        'image_url',
        'name',
        'google_maps_url'
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
        'is_national' => 'boolean',
        'service_areas' => 'array',
        'product_tags' => 'array',
        'business_tags' => 'array',
        'overall_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'rating_averages' => 'array',
        'rating_distribution' => 'array',
        'discovery_score' => 'decimal:2',
        'pending_changes' => 'array',
        'has_pending_changes' => 'boolean',
        'approved_at' => 'datetime',
        'parent_id' => 'integer',
        'embedding' => 'array',
        'embedding_updated_at' => 'datetime',
    ];

    // Accessors
    public function getImageUrlAttribute()
    {
        return $this->logoImage?->image_url;
    }

    /**
     * Get Google Maps URL for this business
     */
    public function getGoogleMapsUrlAttribute()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        // Google Maps URL with coordinates and business name
        return "https://www.google.com/maps/search/" . urlencode($this->business_name) . "/@" . $this->latitude . "," . $this->longitude . ",15z";
    }

    /**
     * Get Google Maps directions URL
     */
    public function getGoogleMapsDirectionsUrl($fromLatitude = null, $fromLongitude = null)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        $destination = $this->latitude . "," . $this->longitude;
        
        if ($fromLatitude && $fromLongitude) {
            $origin = $fromLatitude . "," . $fromLongitude;
            return "https://www.google.com/maps/dir/" . $origin . "/" . $destination;
        }
        
        // If no origin specified, let Google Maps use current location
        return "https://www.google.com/maps/dir//" . $destination;
    }

    /**
     * Get Google Maps place search URL
     */
    public function getGoogleMapsPlaceUrl()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        $query = urlencode($this->business_name . " " . $this->full_address);
        return "https://www.google.com/maps/search/" . $query;
    }

    /**
     * Get Google Maps embed URL for iframe embedding
     */
    public function getGoogleMapsEmbedUrl($apiKey = null, $mapType = 'place')
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        $baseUrl = "https://www.google.com/maps/embed/v1/";
        
        switch ($mapType) {
            case 'place':
                $url = $baseUrl . "place";
                $query = urlencode($this->business_name . " " . $this->area . " " . $this->city);
                break;
            case 'view':
                $url = $baseUrl . "view";
                $query = $this->latitude . "," . $this->longitude;
                break;
            default:
                $url = $baseUrl . "place";
                $query = urlencode($this->business_name . " " . $this->area . " " . $this->city);
        }
        
        $params = [
            'q' => $query,
            'zoom' => '15',
            'maptype' => 'roadmap'
        ];
        
        if ($apiKey) {
            $params['key'] = $apiKey;
        }
        
        return $url . "?" . http_build_query($params);
    }

    /**
     * Get simple Google Maps URL without embed (for direct viewing)
     */
    public function getGoogleMapsSimpleUrl()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        return "https://maps.google.com/?q=" . $this->latitude . "," . $this->longitude;
    }

    /**
     * Get OpenStreetMap URLs (Free alternative)
     */
    public function getOpenStreetMapUrl()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        return "https://www.openstreetmap.org/?mlat=" . $this->latitude . "&mlon=" . $this->longitude . "&zoom=15";
    }

    /**
     * Get Leaflet map data for frontend rendering
     */
    public function getLeafletMapData()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        return [
            'center' => [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude
            ],
            'zoom' => 15,
            'marker' => [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
                'popup' => $this->business_name . '<br>' . $this->full_address
            ],
            'tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '© OpenStreetMap contributors'
        ];
    }

    /**
     * Get MapBox URLs (free tier available)
     */
    public function getMapBoxUrl($accessToken = null)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        $token = $accessToken ?? config('maps.mapbox.access_token');
        
        if (!$token) {
            return null; // No token available
        }
        
        $baseUrl = "https://api.mapbox.com/styles/v1/mapbox/streets-v11/static/pin-s+ff0000({$this->longitude},{$this->latitude})/{$this->longitude},{$this->latitude},15,0/600x400@2x";
        
        return $baseUrl . "?access_token=" . $token;
    }

    /**
     * Get MapBox embed data for frontend
     */
    public function getMapBoxEmbedData()
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        $token = config('maps.mapbox.access_token');
        
        if (!$token) {
            return null;
        }
        
        return [
            'access_token' => $token,
            'style' => config('maps.mapbox.style', 'mapbox://styles/mapbox/streets-v11'),
            'center' => [(float) $this->longitude, (float) $this->latitude], // Note: lng, lat for MapBox
            'zoom' => 15,
            'marker' => [
                'coordinates' => [(float) $this->longitude, (float) $this->latitude],
                'popup' => $this->business_name . '<br>' . $this->full_address
            ]
        ];
    }

    /**
     * Relationships
     */
    public function parentBusiness()
    {
        return $this->belongsTo(Business::class, 'parent_id');
    }

    public function branches()
    {
        return $this->hasMany(Business::class, 'parent_id');
    }

    public function isBranch()
    {
        return $this->parent_id !== null;
    }

    public function isParent()
    {
        return $this->parent_id === null;
    }

    /**
     * Get all offerings for this business, inheriting from the parent brand if none exist locally.
     */
    public function getInheritedOfferings()
    {
        if ($this->offerings()->exists()) {
            return $this->offerings;
        }

        if ($this->parent_id) {
            $parent = $this->parentBusiness;
            if ($parent) {
                return $parent->offerings;
            }
        }

        return collect();
    }

    /**
     * Get inherited products.
     */
    public function getInheritedProducts()
    {
        if ($this->products()->exists()) {
            return $this->products;
        }

        if ($this->parent_id) {
            $parent = $this->parentBusiness;
            if ($parent) {
                return $parent->products;
            }
        }

        return collect();
    }

    /**
     * Get inherited services.
     */
    public function getInheritedServices()
    {
        if ($this->services()->exists()) {
            return $this->services;
        }

        if ($this->parent_id) {
            $parent = $this->parentBusiness;
            if ($parent) {
                return $parent->services;
            }
        }

        return collect();
    }

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

    public function collections()
    {
        return $this->belongsToMany(UserCollection::class, 'collection_items', 'business_id', 'collection_id')
                    ->withPivot(['notes', 'sort_order', 'added_at'])
                    ->withTimestamps();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('businesses.is_active', true);
    }

    /**
     * "Reachable" = an actual physical location a user can visit.
     *
     * Excludes brand-level parent rows (no `parent_id`) that have one
     * or more branches — those rows are virtual brand profiles and
     * showing them in nearby/featured/popular lists is confusing
     * ("KFC Bangladesh" appearing alongside "KFC Dhanmondi").
     *
     * Single-location businesses (no branches, no parent) and the
     * branches themselves both stay visible.
     *
     * Columns are fully qualified (`businesses.parent_id`,
     * `businesses.id`) so the scope can be combined with JOINs (e.g.
     * `trending_data` join used by SearchController) without throwing
     * "Column 'id' in WHERE is ambiguous".
     */
    public function scopeReachable($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('businesses.parent_id') // branches always reachable
                ->orWhereNotExists(function ($sub) {
                    /** @var \Illuminate\Database\Query\Builder $sub */
                    $sub->select(DB::raw(1))
                        ->from('businesses as child_branches')
                        ->whereColumn('child_branches.parent_id', 'businesses.id');
                });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Scout / Meilisearch
    |--------------------------------------------------------------------------
    | The index name is plural snake_case so it lines up with the
    | `index-settings` block in `config/scout.php`. Geo data is exposed
    | in Meilisearch's native `_geo` shape so the mobile app can use
    | `_geoRadius(lat, lng, meters)` filters.
    */

    public function searchableAs(): string
    {
        return 'businesses';
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'business_name' => (string) $this->business_name,
            'description' => (string) ($this->description ?? ''),
            'tags' => is_array($this->tags ?? null) ? array_values($this->tags) : [],
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'business_model' => $this->business_model,
            'city' => (string) ($this->city ?? ''),
            'area' => (string) ($this->area ?? ''),
            'full_address' => (string) ($this->full_address ?? ''),
            'price_range' => $this->price_range,
            'has_delivery' => (bool) ($this->has_delivery ?? false),
            'has_pickup' => (bool) ($this->has_pickup ?? false),
            'has_parking' => (bool) ($this->has_parking ?? false),
            'is_active' => (bool) ($this->is_active ?? false),
            'is_verified' => (bool) ($this->is_verified ?? false),
            'is_featured' => (bool) ($this->is_featured ?? false),
            'is_national' => (bool) ($this->is_national ?? false),
            'overall_rating' => (float) ($this->overall_rating ?? 0),
            'total_reviews' => (int) ($this->total_reviews ?? 0),
            'created_at' => optional($this->created_at)->timestamp,
            '_geo' => $this->latitude !== null && $this->longitude !== null
                ? ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude]
                : null,
        ];
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        // Featured discovery should also hide brand parent rows so
        // featured carousels list the actual locations users can
        // visit, not the brand container.
        $this->scopeReachable($query);

        return $query->where('is_featured', true);
    }

    public function scopeNational($query)
    {
        // National brand listing legitimately wants the brand row,
        // not the branches — that's the whole point of `is_national`.
        // No reachable() filter here.
        return $query->where('is_national', true);
    }

    public function scopeLocal($query)
    {
        return $query->where('is_national', false);
    }

    public function scopeByServiceCoverage($query, $coverage)
    {
        return $query->where('service_coverage', $coverage);
    }

    public function scopeByBusinessModel($query, $model)
    {
        return $query->where('business_model', $model);
    }

    public function scopeServingArea($query, $area)
    {
        return $query->where(function($q) use ($area) {
            $q->where('is_national', true)
              ->orWhere('service_areas', 'LIKE', '%"' . $area . '"%')
              ->orWhere('city', 'LIKE', '%' . $area . '%')
              ->orWhere('area', 'LIKE', '%' . $area . '%');
        });
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Filter to businesses within `$radiusKm` of the given coords and
     * order them by distance ascending. Uses the spatial index on the
     * generated `location` column when running against MySQL; falls back
     * to in-SQL Haversine on SQLite (test runner).
     */
    public function scopeNearby($query, $latitude, $longitude, $radiusKm = 10)
    {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        // Hide brand parent rows (a brand's "near me" never makes
        // sense — only its branches should appear in nearby lists).
        $this->scopeReachable($query);

        $query->whereNotNull('latitude')->whereNotNull('longitude');
        self::applyNearbyFilter($query, 'location', $latitude, $longitude, (float) $radiusKm);
        self::applyDistanceOrder($query, 'location', $latitude, $longitude);

        return $query;
    }

    /**
     * Same as `scopeNearby` but also adds a `distance` column (kilometres).
     */
    public function scopeNearbyWithDistance($query, $latitude, $longitude, $radiusKm = 10)
    {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        $this->scopeReachable($query);
        $query->whereNotNull('latitude')->whereNotNull('longitude');
        self::applyDistanceSelect($query, 'businesses', 'location', $latitude, $longitude, 'distance');
        self::applyNearbyFilter($query, 'location', $latitude, $longitude, (float) $radiusKm);

        return $query->orderBy('distance');
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
     * Columns calculateDiscoveryScore() reads without a user location
     * (besides id). Bulk refreshes select only these; keep in sync.
     */
    public const DISCOVERY_COLUMNS = ['overall_rating'];

    /**
     * Discovery score (0-100). Read-only: this never writes.
     *
     * The distance term only applies when a user location is given, so a
     * location-aware score belongs in a response, not in the database.
     * Pass $activity (from discoveryActivity()) to avoid per-row queries.
     *
     * @param  array{reviews: int, offers: int}|null  $activity
     */
    public function calculateDiscoveryScore($userLatitude = null, $userLongitude = null, ?array $activity = null): float
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

        $activity ??= static::discoveryActivity([$this->id])[$this->id] ?? ['reviews' => 0, 'offers' => 0];

        // Recent review activity (20%)
        $recentActivityScore = min(100, $activity['reviews'] * 10);
        $score += $recentActivityScore * 0.20;

        // Active offers (15%)
        $offersScore = min(100, $activity['offers'] * 25);
        $score += $offersScore * 0.15;

        // User preference match (10%) - can be enhanced based on user behavior
        $preferenceScore = 50; // Base score
        $score += $preferenceScore * 0.10;

        return $score;
    }

    /**
     * Recent (30-day) review counts and active offer counts for many
     * businesses in one query, keyed by business id.
     *
     * @param  iterable<int>  $ids
     * @return array<int, array{reviews: int, offers: int}>
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
                'activeOffers as discovery_active_offers',
            ])
            ->get(['id'])
            ->mapWithKeys(fn ($b) => [$b->id => [
                'reviews' => (int) $b->getAttribute('discovery_recent_reviews'),
                'offers' => (int) $b->getAttribute('discovery_active_offers'),
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

    /**
     * Scope for businesses with specific product tags
     */
    public function scopeWithProductTag($query, $tag)
    {
        return $query->whereJsonContains('product_tags', $tag);
    }

    /**
     * Scope for businesses with any of the provided product tags
     */
    public function scopeWithAnyProductTag($query, $tags)
    {
        $query->where(function($q) use ($tags) {
            foreach ((array) $tags as $tag) {
                $q->orWhereJsonContains('product_tags', $tag);
            }
        });
        return $query;
    }

    /**
     * Scope for businesses with specific business tags
     */
    public function scopeWithBusinessTag($query, $tag)
    {
        return $query->whereJsonContains('business_tags', $tag);
    }

    // UTILITY METHODS

    /**
     * Check if business has a specific product tag
     */
    public function hasProductTag($tag)
    {
        return in_array($tag, $this->product_tags ?? []);
    }

    /**
     * Check if business has any of the provided product tags
     */
    public function hasAnyProductTag($tags)
    {
        $businessTags = $this->product_tags ?? [];
        return !empty(array_intersect($businessTags, (array) $tags));
    }

    /**
     * Check if business has a specific business tag
     */
    public function hasBusinessTag($tag)
    {
        return in_array($tag, $this->business_tags ?? []);
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

    /**
     * Get the business name
     */
    public function getNameAttribute()
    {
        return $this->business_name;
    }
}

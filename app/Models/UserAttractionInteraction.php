<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserAttractionInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attraction_id',
        'interaction_type',
        'interaction_data',
        'notes',
        'visit_info',
        'is_public',
        'user_rating',
        'is_active',
        'interaction_date',
    ];

    protected $casts = [
        'interaction_data' => 'array',
        'visit_info' => 'array',
        'is_public' => 'boolean',
        'user_rating' => 'decimal:1',
        'is_active' => 'boolean',
        'interaction_date' => 'datetime',
    ];

    protected $appends = [
        'time_ago',
        'is_recent',
    ];

    // Interaction types constants
    const TYPE_LIKE = 'like';
    const TYPE_DISLIKE = 'dislike';
    const TYPE_SHARE = 'share';
    const TYPE_BOOKMARK = 'bookmark';
    const TYPE_VISIT = 'visit';
    const TYPE_CHECK_IN = 'check_in';
    const TYPE_WISHLIST = 'wishlist';
    const TYPE_BEEN_THERE = 'been_there';

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attraction()
    {
        return $this->belongsTo(Attraction::class);
    }

    /**
     * Accessors
     */
    public function getTimeAgoAttribute()
    {
        return $this->interaction_date ? 
            $this->interaction_date->diffForHumans() : 
            $this->created_at->diffForHumans();
    }

    public function getIsRecentAttribute()
    {
        $date = $this->interaction_date ?? $this->created_at;
        return $date >= now()->subDays(7);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    public function scopeLikes($query)
    {
        return $query->where('interaction_type', self::TYPE_LIKE);
    }

    public function scopeDislikes($query)
    {
        return $query->where('interaction_type', self::TYPE_DISLIKE);
    }

    public function scopeShares($query)
    {
        return $query->where('interaction_type', self::TYPE_SHARE);
    }

    public function scopeBookmarks($query)
    {
        return $query->where('interaction_type', self::TYPE_BOOKMARK);
    }

    public function scopeVisits($query)
    {
        return $query->where('interaction_type', self::TYPE_VISIT);
    }

    public function scopeCheckIns($query)
    {
        return $query->where('interaction_type', self::TYPE_CHECK_IN);
    }

    public function scopeWishlist($query)
    {
        return $query->where('interaction_type', self::TYPE_WISHLIST);
    }

    public function scopeBeenThere($query)
    {
        return $query->where('interaction_type', self::TYPE_BEEN_THERE);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAttraction($query, $attractionId)
    {
        return $query->where('attraction_id', $attractionId);
    }

    /**
     * Helper Methods
     */
    public static function createOrUpdate($userId, $attractionId, $type, $data = [])
    {
        $interaction = static::where('user_id', $userId)
                           ->where('attraction_id', $attractionId)
                           ->where('interaction_type', $type)
                           ->first();

        if ($interaction) {
            // Update existing interaction
            $interaction->update(array_merge($data, [
                'interaction_date' => now(),
                'is_active' => true
            ]));
        } else {
            // Create new interaction
            $interaction = static::create(array_merge($data, [
                'user_id' => $userId,
                'attraction_id' => $attractionId,
                'interaction_type' => $type,
                'interaction_date' => now()
            ]));
        }

        // Update attraction counters
        $interaction->updateAttractionCounters();

        return $interaction;
    }

    public static function removeInteraction($userId, $attractionId, $type)
    {
        $interaction = static::where('user_id', $userId)
                           ->where('attraction_id', $attractionId)
                           ->where('interaction_type', $type)
                           ->first();

        if ($interaction) {
            $interaction->delete();
            $interaction->updateAttractionCounters();
            return true;
        }

        return false;
    }

    public static function toggleInteraction($userId, $attractionId, $type, $data = [])
    {
        $interaction = static::where('user_id', $userId)
                           ->where('attraction_id', $attractionId)
                           ->where('interaction_type', $type)
                           ->first();

        if ($interaction) {
            // Remove interaction
            $interaction->delete();
            $interaction->updateAttractionCounters();
            return ['action' => 'removed', 'interaction' => null];
        } else {
            // Create interaction
            $newInteraction = static::createOrUpdate($userId, $attractionId, $type, $data);
            return ['action' => 'created', 'interaction' => $newInteraction];
        }
    }

    public static function getUserInteractionTypes($userId, $attractionId)
    {
        return static::where('user_id', $userId)
                    ->where('attraction_id', $attractionId)
                    ->pluck('interaction_type')
                    ->toArray();
    }

    public static function hasUserInteraction($userId, $attractionId, $type)
    {
        return static::where('user_id', $userId)
                    ->where('attraction_id', $attractionId)
                    ->where('interaction_type', $type)
                    ->exists();
    }

    public function updateAttractionCounters()
    {
        $attraction = $this->attraction;
        
        if ($attraction) {
            $likesCount = static::where('attraction_id', $attraction->id)
                               ->where('interaction_type', self::TYPE_LIKE)
                               ->count();
            
            $dislikesCount = static::where('attraction_id', $attraction->id)
                                  ->where('interaction_type', self::TYPE_DISLIKE)
                                  ->count();
            
            $sharesCount = static::where('attraction_id', $attraction->id)
                                ->where('interaction_type', self::TYPE_SHARE)
                                ->count();

            $attraction->update([
                'total_likes' => $likesCount,
                'total_dislikes' => $dislikesCount,
                'total_shares' => $sharesCount,
            ]);
        }

        return $this;
    }

    public function isPositive()
    {
        return in_array($this->interaction_type, [
            self::TYPE_LIKE,
            self::TYPE_BOOKMARK,
            self::TYPE_WISHLIST,
            self::TYPE_BEEN_THERE,
            self::TYPE_CHECK_IN
        ]);
    }

    public function isNegative()
    {
        return $this->interaction_type === self::TYPE_DISLIKE;
    }

    public function isNeutral()
    {
        return in_array($this->interaction_type, [
            self::TYPE_SHARE,
            self::TYPE_VISIT
        ]);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        // Update attraction counters when interaction is created/deleted
        static::created(function ($interaction) {
            $interaction->updateAttractionCounters();
        });
        
        static::deleted(function ($interaction) {
            $interaction->updateAttractionCounters();
        });
    }
}

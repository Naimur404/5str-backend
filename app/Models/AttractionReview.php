<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttractionReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'attraction_id',
        'user_id',
        'rating',
        'title',
        'comment',
        'visit_info',
        'experience_tags',
        'is_verified',
        'is_featured',
        'is_anonymous',
        'helpful_votes',
        'total_votes',
        'status',
        'admin_notes',
        'visit_date',
        'meta_data',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'visit_info' => 'array',
        'experience_tags' => 'array',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'is_anonymous' => 'boolean',
        'helpful_votes' => 'integer',
        'total_votes' => 'integer',
        'visit_date' => 'datetime',
        'meta_data' => 'array',
    ];

    protected $appends = [
        'helpful_percentage',
        'time_ago',
        'is_recent',
    ];

    /**
     * Relationships
     */
    public function attraction()
    {
        return $this->belongsTo(Attraction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function helpfulVotes()
    {
        return $this->hasMany(AttractionReviewHelpfulVote::class, 'review_id');
    }

    public function images()
    {
        return $this->hasMany(AttractionReviewImage::class, 'review_id');
    }

    /**
     * Accessors
     */
    public function getHelpfulPercentageAttribute()
    {
        if ($this->total_votes == 0) {
            return 0;
        }
        
        return round(($this->helpful_votes / $this->total_votes) * 100, 1);
    }

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getIsRecentAttribute()
    {
        return $this->created_at >= now()->subDays(30);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeWithRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeMinRating($query, $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeHelpful($query)
    {
        return $query->where('helpful_votes', '>', 0)->orderBy('helpful_votes', 'desc');
    }

    public function scopeByExperienceTag($query, $tag)
    {
        return $query->whereJsonContains('experience_tags', $tag);
    }

    /**
     * Helper Methods
     */
    public function markAsHelpful($userId)
    {
        // Check if user already voted
        $existingVote = AttractionReviewHelpfulVote::where('review_id', $this->id)
                                                   ->where('user_id', $userId)
                                                   ->first();
        
        if ($existingVote) {
            return false; // Already voted
        }
        
        // Create helpful vote
        AttractionReviewHelpfulVote::create([
            'review_id' => $this->id,
            'user_id' => $userId,
            'is_helpful' => true
        ]);
        
        // Update counters
        $this->increment('helpful_votes');
        $this->increment('total_votes');
        
        return true;
    }

    public function markAsNotHelpful($userId)
    {
        // Check if user already voted
        $existingVote = AttractionReviewHelpfulVote::where('review_id', $this->id)
                                                   ->where('user_id', $userId)
                                                   ->first();
        
        if ($existingVote) {
            return false; // Already voted
        }
        
        // Create not helpful vote
        AttractionReviewHelpfulVote::create([
            'review_id' => $this->id,
            'user_id' => $userId,
            'is_helpful' => false
        ]);
        
        // Update total votes counter
        $this->increment('total_votes');
        
        return true;
    }

    public function hasUserVoted($userId)
    {
        return AttractionReviewHelpfulVote::where('review_id', $this->id)
                                          ->where('user_id', $userId)
                                          ->exists();
    }

    public function getUserVote($userId)
    {
        return AttractionReviewHelpfulVote::where('review_id', $this->id)
                                          ->where('user_id', $userId)
                                          ->first();
    }

    public function hasExperienceTag($tag)
    {
        return in_array($tag, $this->experience_tags ?? []);
    }

    public function getVisitMonth()
    {
        return $this->visit_date ? $this->visit_date->format('F Y') : null;
    }

    public function getVisitSeason()
    {
        if (!$this->visit_date) {
            return null;
        }
        
        $month = $this->visit_date->month;
        
        if (in_array($month, [12, 1, 2])) return 'Winter';
        if (in_array($month, [3, 4, 5])) return 'Spring';
        if (in_array($month, [6, 7, 8])) return 'Summer';
        if (in_array($month, [9, 10, 11])) return 'Autumn';
        
        return null;
    }

    /**
     * Update attraction's overall rating after review changes
     */
    public function updateAttractionRating()
    {
        $this->attraction->updateRating();
        return $this;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        // Update attraction rating when review is created/updated/deleted
        static::created(function ($review) {
            $review->updateAttractionRating();
        });
        
        static::updated(function ($review) {
            $review->updateAttractionRating();
        });
        
        static::deleted(function ($review) {
            $review->updateAttractionRating();
        });
    }
}

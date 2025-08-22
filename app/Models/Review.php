<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Review Model
 * 
 * This model automatically handles:
 * 1. Rating calculations for businesses and offerings when reviews are created/updated/deleted
 * 2. User points awarding based on review quality (length, categories, pros/cons, verified visits)
 * 3. Points deduction when reviews are deleted
 * 
 * Points calculation criteria:
 * - Base points: 10 for any approved review
 * - Detailed review (100+ chars): +5 points
 * - Multiple rating categories: +2 points each
 * - Pros provided: +3 points  
 * - Cons provided: +3 points
 * - Verified visit: +5 points
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reviewable_id',
        'reviewable_type',
        'overall_rating',
        'service_rating',
        'quality_rating',
        'value_rating',
        'title',
        'review_text',
        'pros',
        'cons',
        'visit_date',
        'amount_spent',
        'party_size',
        'is_recommended',
        'is_verified_visit',
        'helpful_count',
        'not_helpful_count',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'service_rating' => 'integer',
        'quality_rating' => 'integer',
        'value_rating' => 'integer',
        'pros' => 'array',
        'cons' => 'array',
        'visit_date' => 'date',
        'amount_spent' => 'decimal:2',
        'party_size' => 'integer',
        'is_recommended' => 'boolean',
        'is_verified_visit' => 'boolean',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }

    public function helpfulVotes()
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeHighRated($query, $minRating = 4)
    {
        return $query->where('overall_rating', '>=', $minRating);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeWithImages($query)
    {
        return $query->has('images');
    }

    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }

    /**
     * Calculate review quality score
     */
    public function calculateQualityScore()
    {
        $score = 0;

        // Review length and detail (40%)
        $textLength = strlen($this->review_text);
        $lengthScore = min(100, ($textLength / 200) * 100); // 200 chars = full score
        $score += $lengthScore * 0.40;

        // Photos included (25%)
        $photoScore = $this->images()->count() > 0 ? 100 : 0;
        $score += $photoScore * 0.25;

        // Helpful votes received (20%)
        $helpfulRatio = $this->helpful_count + $this->not_helpful_count > 0 
            ? ($this->helpful_count / ($this->helpful_count + $this->not_helpful_count)) * 100 
            : 50;
        $score += $helpfulRatio * 0.20;

        // User trust level (10%)
        $userTrustScore = ($this->user->trust_level / 5) * 100;
        $score += $userTrustScore * 0.10;

        // Verified visit (5%)
        $verifiedScore = $this->is_verified_visit ? 100 : 0;
        $score += $verifiedScore * 0.05;

        return round($score, 2);
    }

    /**
     * Check if user has voted on this review
     */
    public function hasUserVoted($userId)
    {
        return $this->helpfulVotes()->where('user_id', $userId)->exists();
    }

    /**
     * Get user's vote on this review
     */
    public function getUserVote($userId)
    {
        $vote = $this->helpfulVotes()->where('user_id', $userId)->first();
        return $vote ? $vote->is_helpful : null;
    }

    /**
     * Boot method for model events
     */
    protected static function booted()
    {
        // When a review is created
        static::created(function (Review $review) {
            $review->updateRelatedRatings();
            $review->awardUserPoints();
        });

        // When a review is updated
        static::updated(function (Review $review) {
            // Only update ratings if the overall_rating or status changed
            if ($review->wasChanged(['overall_rating', 'status'])) {
                $review->updateRelatedRatings();
            }
        });

        // When a review is deleted
        static::deleted(function (Review $review) {
            $review->updateRelatedRatings();
            $review->deductUserPoints();
        });
    }

    /**
     * Update ratings for the reviewed item (business or offering)
     */
    public function updateRelatedRatings()
    {
        $reviewable = $this->reviewable;
        if (!$reviewable) return;

        // Get all approved reviews for this item
        $reviews = Review::where('reviewable_type', $this->reviewable_type)
            ->where('reviewable_id', $this->reviewable_id)
            ->where('status', 'approved')
            ->get();

        $totalReviews = $reviews->count();
        $averageRating = $totalReviews > 0 ? $reviews->avg('overall_rating') : 0;

        // Update the reviewable item
        if ($this->reviewable_type === 'App\\Models\\Business') {
            $reviewable->update([
                'overall_rating' => round($averageRating, 2),
                'total_reviews' => $totalReviews
            ]);
        } elseif ($this->reviewable_type === 'App\\Models\\BusinessOffering') {
            $reviewable->update([
                'average_rating' => round($averageRating, 2),
                'total_reviews' => $totalReviews
            ]);
        }
    }

    /**
     * Award points to user for writing a review
     */
    public function awardUserPoints()
    {
        if ($this->status !== 'approved') return;

        $points = $this->calculateReviewPoints();
        
        if ($points > 0) {
            UserPoint::create([
                'user_id' => $this->user_id,
                'points' => $points,
                'point_type' => 'review',
                'reference_id' => $this->id,
                'reference_type' => get_class($this),
                'description' => $this->getPointsDescription()
            ]);

            // Update user's total points
            $this->updateUserTotalPoints();
        }
    }

    /**
     * Deduct points when review is deleted
     */
    public function deductUserPoints()
    {
        // Remove the points entry for this review
        UserPoint::where('reference_type', get_class($this))
            ->where('reference_id', $this->id)
            ->delete();

        // Update user's total points
        $this->updateUserTotalPoints();
    }

    /**
     * Calculate points for this review
     */
    private function calculateReviewPoints()
    {
        $points = 10; // Base points for any review

        // Bonus points for detailed reviews
        if (strlen($this->review_text) > 100) {
            $points += 5;
        }

        // Bonus points for ratings in multiple categories
        $ratingCategories = collect([
            $this->food_rating,
            $this->service_rating,
            $this->ambiance_rating,
            $this->value_rating,
            $this->quality_rating
        ])->filter()->count();
        
        $points += $ratingCategories * 2;

        // Bonus points for adding pros/cons
        if ($this->pros && count($this->pros) > 0) {
            $points += 3;
        }
        if ($this->cons && count($this->cons) > 0) {
            $points += 3;
        }

        // Bonus for verified visits
        if ($this->is_verified_visit) {
            $points += 5;
        }

        return $points;
    }

    /**
     * Get description for points earning
     */
    private function getPointsDescription()
    {
        $reviewable = $this->reviewable;
        $itemName = 'item';
        
        if ($this->reviewable_type === 'App\\Models\\Business') {
            $itemName = $reviewable->business_name ?? 'business';
        } elseif ($this->reviewable_type === 'App\\Models\\BusinessOffering') {
            $itemName = $reviewable->name ?? 'offering';
        }

        return "Review written for {$itemName}";
    }

    /**
     * Update user's total points
     */
    private function updateUserTotalPoints()
    {
        $totalPoints = UserPoint::where('user_id', $this->user_id)->sum('points');
        
        $this->user->update([
            'total_points' => $totalPoints
        ]);
    }
}

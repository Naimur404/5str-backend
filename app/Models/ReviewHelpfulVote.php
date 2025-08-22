<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewHelpfulVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'user_id',
        'is_helpful'
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
    ];

    // Boot method for automatic points calculation
    protected static function booted()
    {
        static::created(function ($vote) {
            $vote->awardPointsForHelpfulVote();
        });

        static::updated(function ($vote) {
            $vote->awardPointsForHelpfulVote();
        });

        static::deleted(function ($vote) {
            $vote->removePointsForHelpfulVote();
        });
    }

    // Relationships
    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Award points for helpful votes
    private function awardPointsForHelpfulVote()
    {
        if ($this->is_helpful) {
            // Award points to the review author when their review gets helpful vote
            $reviewAuthor = $this->review->user;
            if ($reviewAuthor) {
                $reviewAuthor->increment('total_points', 2); // +2 points for helpful vote
            }
        }
    }

    // Remove points for helpful votes
    private function removePointsForHelpfulVote()
    {
        if ($this->is_helpful) {
            // Remove points from the review author when helpful vote is removed
            $reviewAuthor = $this->review->user;
            if ($reviewAuthor) {
                $reviewAuthor->decrement('total_points', 2); // -2 points for removed helpful vote
            }
        }
    }
}

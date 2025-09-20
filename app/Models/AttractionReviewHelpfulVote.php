<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttractionReviewHelpfulVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'user_id',
        'is_helpful',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function review()
    {
        return $this->belongsTo(AttractionReview::class, 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeHelpful($query)
    {
        return $query->where('is_helpful', true);
    }

    public function scopeNotHelpful($query)
    {
        return $query->where('is_helpful', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByReview($query, $reviewId)
    {
        return $query->where('review_id', $reviewId);
    }

    /**
     * Helper Methods
     */
    public static function toggleVote($reviewId, $userId, $isHelpful = true)
    {
        $existingVote = self::where('review_id', $reviewId)
                           ->where('user_id', $userId)
                           ->first();

        if ($existingVote) {
            if ($existingVote->is_helpful === $isHelpful) {
                // Same vote - remove it
                $existingVote->delete();
                return 'removed';
            } else {
                // Different vote - update it
                $existingVote->update(['is_helpful' => $isHelpful]);
                return 'updated';
            }
        } else {
            // New vote - create it
            self::create([
                'review_id' => $reviewId,
                'user_id' => $userId,
                'is_helpful' => $isHelpful,
            ]);
            return 'created';
        }
    }

    public static function getUserVote($reviewId, $userId)
    {
        return self::where('review_id', $reviewId)
                   ->where('user_id', $userId)
                   ->first();
    }
}

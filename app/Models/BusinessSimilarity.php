<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection;

class BusinessSimilarity extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_a_id',
        'business_b_id',
        'similarity_type',
        'similarity_score',
        'contributing_factors',
        'calculated_at'
    ];

    protected $casts = [
        'similarity_score' => 'decimal:4',
        'contributing_factors' => 'array',
        'calculated_at' => 'datetime'
    ];

    public function businessA(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_a_id');
    }

    public function businessB(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_b_id');
    }

    public static function getSimilarBusinesses(int $businessId, string $type = null, float $minScore = 0.5): \Illuminate\Support\Collection
    {
        $query = self::query()
            ->where(function ($q) use ($businessId) {
                $q->where('business_a_id', $businessId)
                  ->orWhere('business_b_id', $businessId);
            })
            ->where('similarity_score', '>=', $minScore);

        if ($type) {
            $query->where('similarity_type', $type);
        }

        return $query->get()->map(function ($similarity) use ($businessId) {
            // Ensure proper type casting for comparison
            $businessAId = (int) $similarity->business_a_id;
            $businessBId = (int) $similarity->business_b_id;
            $requestedId = (int) $businessId;
            
            // DEBUG: Log the comparison for troubleshooting
            \Illuminate\Support\Facades\Log::info("DEBUG BusinessSimilarity ID calculation", [
                'raw_a_id' => $similarity->business_a_id,
                'raw_b_id' => $similarity->business_b_id,
                'requested_id' => $businessId,
                'cast_a_id' => $businessAId,
                'cast_b_id' => $businessBId,
                'cast_requested_id' => $requestedId,
                'comparison_result' => $businessAId === $requestedId
            ]);
            
            $otherBusinessId = $businessAId === $requestedId 
                ? $businessBId 
                : $businessAId;

            return [
                'business_id' => $otherBusinessId,
                'similarity_score' => $similarity->similarity_score,
                'similarity_type' => $similarity->similarity_type,
                'contributing_factors' => $similarity->contributing_factors
            ];
        });
    }

    public static function calculateAndStore(int $businessAId, int $businessBId, array $factors): void
    {
        $score = self::calculateSimilarityScore($factors);
        $type = self::determineSimilarityType($factors);

        self::updateOrCreate([
            'business_a_id' => min($businessAId, $businessBId),
            'business_b_id' => max($businessAId, $businessBId),
            'similarity_type' => $type
        ], [
            'similarity_score' => $score,
            'contributing_factors' => $factors,
            'calculated_at' => now()
        ]);
    }

    private static function calculateSimilarityScore(array $factors): float
    {
        $weights = [
            'category_match' => 0.3,
            'location_proximity' => 0.25,
            'review_sentiment' => 0.15,
            'feature_overlap' => 0.15,
            'user_overlap' => 0.15
        ];

        $score = 0;
        foreach ($weights as $factor => $weight) {
            $score += ($factors[$factor] ?? 0) * $weight;
        }

        return round($score, 4);
    }

    private static function determineSimilarityType(array $factors): string
    {
        if ($factors['category_match'] > 0.8) {
            return 'category_similar';
        } elseif ($factors['location_proximity'] > 0.8) {
            return 'location_similar';
        } elseif ($factors['user_overlap'] > 0.7) {
            return 'user_behavior_similar';
        } else {
            return 'general_similar';
        }
    }
}

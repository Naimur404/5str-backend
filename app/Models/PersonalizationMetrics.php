<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalizationMetrics extends Model
{
    use HasFactory;

    public $timestamps = false; // Using only created_at

    protected $fillable = [
        'user_id',
        'personalization_level',
        'response_time_ms',
        'recommendation_count',
        'metrics',
        'session_id'
    ];

    protected $casts = [
        'metrics' => 'array',
        'response_time_ms' => 'decimal:2',
        'created_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record personalization metrics
     */
    public static function record(
        int $userId,
        string $personalizationLevel,
        float $responseTimeMs,
        int $recommendationCount,
        array $additionalMetrics = [],
        string $sessionId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'personalization_level' => $personalizationLevel,
            'response_time_ms' => $responseTimeMs,
            'recommendation_count' => $recommendationCount,
            'metrics' => $additionalMetrics,
            'session_id' => $sessionId,
            'created_at' => now()
        ]);
    }

    /**
     * Get performance analytics by personalization level
     */
    public static function getPerformanceAnalytics(int $days = 7): array
    {
        $metrics = self::where('created_at', '>=', now()->subDays($days))
            ->get()
            ->groupBy('personalization_level');

        $analytics = [];
        
        foreach ($metrics as $level => $records) {
            $analytics[$level] = [
                'total_requests' => $records->count(),
                'avg_response_time' => round($records->avg('response_time_ms'), 2),
                'avg_recommendation_count' => round($records->avg('recommendation_count'), 1),
                'min_response_time' => $records->min('response_time_ms'),
                'max_response_time' => $records->max('response_time_ms'),
                'unique_users' => $records->pluck('user_id')->unique()->count(),
                'total_recommendations' => $records->sum('recommendation_count')
            ];
        }

        return $analytics;
    }

    /**
     * Get user's personalization performance
     */
    public static function getUserPerformance(int $userId, int $days = 30): array
    {
        $metrics = self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get()
            ->groupBy('personalization_level');

        $performance = [];
        
        foreach ($metrics as $level => $records) {
            $performance[$level] = [
                'request_count' => $records->count(),
                'avg_response_time' => round($records->avg('response_time_ms'), 2),
                'total_recommendations' => $records->sum('recommendation_count'),
                'last_used' => $records->max('created_at')
            ];
        }

        return $performance;
    }
}

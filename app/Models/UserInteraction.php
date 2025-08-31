<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'interaction_type',
        'source',
        'context_data',
        'weight',
        'session_id',
        'user_latitude',
        'user_longitude',
        'interaction_time'
    ];

    protected $casts = [
        'context_data' => 'array',
        'weight' => 'decimal:2',
        'user_latitude' => 'decimal:8',
        'user_longitude' => 'decimal:8',
        'interaction_time' => 'datetime'
    ];

    public static $interactionWeights = [
        'view' => 1.0,
        'search_click' => 1.5,
        'phone_call' => 5.0,
        'favorite' => 3.0,
        'unfavorite' => -2.0,
        'review' => 4.0,
        'share' => 3.5,
        'collection_add' => 4.5,
        'collection_remove' => -2.5,
        'offer_view' => 2.0,
        'offer_use' => 5.0,
        'direction_request' => 3.0,
        'website_click' => 2.5
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function track(
        int $userId,
        int $businessId,
        string $interactionType,
        string $source = null,
        array $contextData = null,
        float $customWeight = null,
        float $userLatitude = null,
        float $userLongitude = null,
        string $sessionId = null
    ): self {
        $weight = $customWeight ?? self::$interactionWeights[$interactionType] ?? 1.0;

        $data = [
            'user_id' => $userId,
            'business_id' => $businessId,
            'interaction_type' => $interactionType,
            'source' => $source,
            'context_data' => $contextData,
            'weight' => $weight,
            'interaction_time' => now()
        ];

        // Add location data if provided
        if ($userLatitude !== null && $userLongitude !== null) {
            $data['user_latitude'] = $userLatitude;
            $data['user_longitude'] = $userLongitude;
        }

        // Add session ID if provided
        if ($sessionId !== null) {
            $data['session_id'] = $sessionId;
        }

        return self::create($data);
    }

    /**
     * Get interaction summary for user
     */
    public static function getSummaryForUser(int $userId, int $days = 30): array
    {
        $interactions = self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'total_interactions' => $interactions->count(),
            'total_value' => $interactions->sum('weight'),
            'by_type' => $interactions->groupBy('interaction_type')
                ->map(fn($group) => [
                    'count' => $group->count(),
                    'total_value' => $group->sum('weight')
                ]),
            'unique_businesses' => $interactions->pluck('business_id')->unique()->count()
        ];
    }
}

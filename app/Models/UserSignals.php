<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Rolled-up affinity signals per identity (anon_id or user_id).
 *
 * Canonical `signals` JSON shape (each key independently optional;
 * older deployments may not have all of them):
 *
 *   {
 *     "top_categories":  [{"id":12,"score":0.42}, ...],     // weighted recency
 *     "top_areas":       [{"area":"Agrabad","score":0.31}], // where they engage
 *     "top_brands":      [{"id":42,"score":0.18}],          // parent business affinity
 *     "price_range":     2,                                  // 1..4
 *     "time_of_day":     {"morning":0.1,"afternoon":0.4,"evening":0.5},
 *     "session_length":  "short"|"medium"|"long",
 *     "last_seen":       "2026-05-23T10:32:01Z"
 *   }
 *
 * Updated on a schedule by `UserSignalsService::refreshAll()`. Read
 * by `RecommendationService` to bias the home / search / suggestions
 * feeds.
 */
class UserSignals extends Model
{
    use HasFactory;

    protected $table = 'user_signals';

    protected $fillable = [
        'anon_id',
        'user_id',
        'signals',
        'event_count',
        'rolled_at',
    ];

    protected $casts = [
        'signals' => 'array',
        'event_count' => 'integer',
        'rolled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

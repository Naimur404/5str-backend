<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint',
        'user_id',
        'user_area',
        'latitude',
        'longitude',
        'ip_address',
        'user_agent',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get the user that made the request
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by endpoint
     */
    public function scopeEndpoint($query, $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }

    /**
     * Scope to filter by user area
     */
    public function scopeUserArea($query, $area)
    {
        return $query->where('user_area', $area);
    }

    /**
     * Get analytics summary for a specific endpoint
     */
    public static function getEndpointSummary($endpoint, $days = 30)
    {
        $startDate = now()->subDays($days);
        
        return static::where('endpoint', $endpoint)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT user_area) as unique_areas,
                COUNT(DISTINCT ip_address) as unique_ips
            ')
            ->first();
    }

    /**
     * Get top areas by endpoint usage
     */
    public static function getTopAreasByEndpoint($endpoint, $limit = 10)
    {
        return static::where('endpoint', $endpoint)
            ->whereNotNull('user_area')
            ->selectRaw('user_area, COUNT(*) as requests_count')
            ->groupBy('user_area')
            ->orderByDesc('requests_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get endpoint usage trends over time
     */
    public static function getUsageTrends($endpoint, $days = 30)
    {
        $startDate = now()->subDays($days);
        
        return static::where('endpoint', $endpoint)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get most popular endpoints
     */
    public static function getPopularEndpoints($days = 30, $limit = 10)
    {
        $startDate = now()->subDays($days);
        
        return static::where('created_at', '>=', $startDate)
            ->selectRaw('endpoint, COUNT(*) as requests_count, COUNT(DISTINCT user_id) as unique_users')
            ->groupBy('endpoint')
            ->orderByDesc('requests_count')
            ->limit($limit)
            ->get();
    }
}

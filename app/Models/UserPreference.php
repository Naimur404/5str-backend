<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'preferred_categories',
        'category_weights',
        'min_price_range',
        'max_price_range',
        'preferred_radius',
        'preferred_areas',
        'prefers_delivery',
        'prefers_pickup',
        'prefers_parking',
        'prefers_verified',
        'min_rating',
        'preferred_hours',
        'search_frequency',
        'review_frequency',
        'collection_frequency',
        'ml_weights',
        'last_updated'
    ];

    protected $casts = [
        'preferred_categories' => 'array',
        'category_weights' => 'array',
        'preferred_areas' => 'array',
        'preferred_hours' => 'array',
        'ml_weights' => 'array',
        'prefers_delivery' => 'boolean',
        'prefers_pickup' => 'boolean',
        'prefers_parking' => 'boolean',
        'prefers_verified' => 'boolean',
        'min_price_range' => 'integer',
        'max_price_range' => 'integer',
        'preferred_radius' => 'decimal:2',
        'min_rating' => 'decimal:2',
        'last_updated' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCategoryPreferenceScore(int $categoryId): float
    {
        $weights = $this->category_weights ?? [];
        return $weights[$categoryId] ?? 0.0;
    }
}

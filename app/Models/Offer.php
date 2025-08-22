<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'offer_type',
        'discount_percentage',
        'discount_amount',
        'minimum_spend',
        'offer_code',
        'usage_limit',
        'current_usage',
        'valid_from',
        'valid_to',
        'applicable_days',
        'banner_image',
        'is_featured',
        'is_active',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'conditions' => 'array',
        'terms' => 'array',
        'applicable_days' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'minimum_spend' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function usages()
    {
        return $this->hasMany(UserOfferUsage::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('valid_from', '<=', now())
                    ->where('valid_to', '>=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Check if offer is valid today
     */
    public function isValidToday()
    {
        $today = now()->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
        
        if (!$this->applicable_days) {
            return true; // No restriction
        }

        return in_array($today, $this->applicable_days);
    }

    /**
     * Check if user can use this offer
     */
    public function canBeUsedBy($userId)
    {
        if (!$this->isValidToday()) {
            return false;
        }

        if ($this->usage_limit && $this->current_usage >= $this->usage_limit) {
            return false;
        }

        return true;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferingSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'business_name',
        'business_address',
        'offering_name',
        'offering_description',
        'offering_category',
        'price',
        'price_type',
        'availability',
        'contact_info',
        'images',
        'additional_info',
        'status',
        'submission_type',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'images' => 'array',
        'reviewed_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    /**
     * Get the user who submitted this offering
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the business this offering is for (if specified)
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the admin who reviewed this submission
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending submissions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved submissions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected submissions
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Get image URLs
     */
    public function getImageUrlsAttribute()
    {
        if (!$this->images) {
            return [];
        }

        return collect($this->images)->map(function ($image) {
            return url('storage/' . $image);
        })->toArray();
    }
}
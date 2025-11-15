<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttractionSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'type',
        'address',
        'city',
        'latitude',
        'longitude',
        'entry_fee',
        'visiting_hours',
        'best_time_to_visit',
        'facilities',
        'images',
        'additional_info',
        'status',
        'submission_type',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'visiting_hours' => 'array',
        'facilities' => 'array',
        'images' => 'array',
        'reviewed_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'entry_fee' => 'decimal:2',
    ];

    /**
     * Get the user who submitted this attraction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
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
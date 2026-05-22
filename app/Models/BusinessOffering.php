<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class BusinessOffering extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'offering_type',
        'category_id',
        'price',
        'price_max',
        'currency',
        'image_url',
        'is_available',
        'is_popular',
        'is_featured',
        'average_rating',
        'total_reviews',
        'sort_order',
        'approval_status',
        'pending_changes',
        'approved_by',
        'approved_at',
        'admin_notes',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'offering_type' => 'string',
        'price' => 'decimal:2',
        'price_max' => 'decimal:2',
        'is_available' => 'boolean',
        'is_popular' => 'boolean',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'sort_order' => 'integer',
        'approval_status' => 'string',
        'pending_changes' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(OfferingVariant::class, 'offering_id');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scopes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeProducts($query)
    {
        return $query->where('offering_type', 'product');
    }

    public function scopeServices($query)
    {
        return $query->where('offering_type', 'service');
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get price range as formatted string
     */
    public function getPriceRangeAttribute()
    {
        if ($this->price_max && $this->price_max > $this->price) {
            return $this->currency . ' ' . number_format($this->price, 2) . ' - ' . number_format($this->price_max, 2);
        }
        
        return $this->price ? $this->currency . ' ' . number_format($this->price, 2) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Scout / Meilisearch
    |--------------------------------------------------------------------------
    */

    public function searchableAs(): string
    {
        return 'business_offerings';
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'description' => (string) ($this->description ?? ''),
            'business_id' => $this->business_id,
            'category_id' => $this->category_id,
            'price' => $this->price !== null ? (float) $this->price : null,
            'is_active' => (bool) ($this->is_active ?? false),
            'average_rating' => (float) ($this->average_rating ?? 0),
            'total_reviews' => (int) ($this->total_reviews ?? 0),
            'created_at' => optional($this->created_at)->timestamp,
        ];
    }
}

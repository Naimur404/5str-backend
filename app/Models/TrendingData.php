<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendingData extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_type',
        'item_id',
        'item_name',
        'location_area',
        'trend_score',
        'hybrid_score',
        'search_count',
        'view_count',
        'time_period',
        'date_period',
    ];

    protected $casts = [
        'trend_score' => 'decimal:2',
        'hybrid_score' => 'decimal:2',
        'search_count' => 'integer',
        'view_count' => 'integer',
        'date_period' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'item_id');
    }

    public function offering()
    {
        return $this->belongsTo(BusinessOffering::class, 'item_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'item_id');
    }

    // Accessor to get the actual related model based on item_type
    public function getRelatedItemAttribute()
    {
        return match($this->item_type) {
            'business' => $this->business,
            'offering' => $this->offering,
            'category' => $this->category,
            default => null
        };
    }

    // Accessor to get the display name of the related item
    public function getItemDisplayNameAttribute()
    {
        $relatedItem = $this->related_item;
        
        if (!$relatedItem) {
            return $this->item_name;
        }

        return match($this->item_type) {
            'business' => $relatedItem->business_name ?? $this->item_name,
            'offering' => $relatedItem->name ?? $this->item_name,
            'category' => $relatedItem->category_name ?? $this->item_name,
            default => $this->item_name
        };
    }
}

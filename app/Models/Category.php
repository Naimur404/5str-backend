<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'level',
        'icon_image',
        'banner_image',
        'description',
        'color_code',
        'sort_order',
        'is_featured',
        'is_popular',
        'is_active',
        'total_businesses',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'level' => 'integer',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'total_businesses' => 'integer',
    ];

    /**
     * Relationships
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    public function businessCategories()
    {
        return $this->hasMany(BusinessCategory::class);
    }

    public function businessOfferings()
    {
        return $this->hasMany(BusinessOffering::class);
    }

    public function searchLogs()
    {
        return $this->hasMany(SearchLog::class);
    }

    public function trendingData()
    {
        return $this->hasMany(TrendingData::class, 'item_id')->where('item_type', 'category');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeMainCategories($query)
    {
        return $query->where('level', 1);
    }

    public function scopeSubCategories($query)
    {
        return $query->where('level', 2);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set the level when creating/updating a category
        static::creating(function ($category) {
            $category->level = $category->calculateLevel();
        });

        static::updating(function ($category) {
            if ($category->isDirty('parent_id')) {
                $category->level = $category->calculateLevel();
            }
        });
    }

    /**
     * Calculate the level based on parent hierarchy
     */
    public function calculateLevel()
    {
        if (!$this->parent_id) {
            return 1; // Root level
        }

        $parent = Category::find($this->parent_id);
        if (!$parent) {
            return 1; // Default to root if parent not found
        }

        return $parent->level + 1;
    }
}

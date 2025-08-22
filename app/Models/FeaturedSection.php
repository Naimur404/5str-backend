<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeaturedSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_name',
        'title',
        'category_filter',
        'display_limit',
        'sort_criteria',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'category_filter' => 'array',
        'display_limit' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

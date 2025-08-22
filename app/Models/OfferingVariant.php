<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferingVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'offering_id',
        'variant_name',
        'variant_value',
        'price_adjustment',
        'is_available',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function offering()
    {
        return $this->belongsTo(BusinessOffering::class, 'offering_id');
    }

    /**
     * Scopes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}

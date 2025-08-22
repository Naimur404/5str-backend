<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'search_term',
        'category_id',
        'user_latitude',
        'user_longitude',
        'filters_applied',
        'results_count',
        'clicked_business_id',
    ];

    protected $casts = [
        'user_latitude' => 'decimal:8',
        'user_longitude' => 'decimal:8',
        'filters_applied' => 'array',
        'results_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function clickedBusiness()
    {
        return $this->belongsTo(Business::class, 'clicked_business_id');
    }
}

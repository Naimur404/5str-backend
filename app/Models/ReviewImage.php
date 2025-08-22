<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'image_url',
        'alt_text',
        'sort_order',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Relationships
     */
    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}

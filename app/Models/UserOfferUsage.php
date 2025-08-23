<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOfferUsage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_offer_usage';

    protected $fillable = [
        'user_id',
        'offer_id',
        'used_at',
        'usage_count',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class View extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'viewable_type',
        'viewable_id',
        'user_latitude',
        'user_longitude',
        'user_area',
        'ip_address',
        'user_agent',
        'session_id'
    ];

    protected $casts = [
        'user_latitude' => 'decimal:8',
        'user_longitude' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}

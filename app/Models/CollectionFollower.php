<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionFollower extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'user_id',
        'followed_at'
    ];

    protected $casts = [
        'followed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function collection(): BelongsTo
    {
        return $this->belongsTo(UserCollection::class, 'collection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('followed_at', 'desc')->limit($limit);
    }
}

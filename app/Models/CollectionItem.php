<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'business_id',
        'notes',
        'sort_order',
        'added_at'
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function collection(): BelongsTo
    {
        return $this->belongsTo(UserCollection::class, 'collection_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('added_at', 'desc');
    }
}

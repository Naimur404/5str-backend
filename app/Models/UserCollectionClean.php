<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
        'cover_image',
        'slug'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'businesses_count',
        'followers_count'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class, 'collection_id')
                    ->orderBy('sort_order')
                    ->orderBy('added_at', 'desc');
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'collection_items', 'collection_id', 'business_id')
                    ->withPivot(['notes', 'sort_order', 'added_at'])
                    ->withTimestamps()
                    ->orderBy('collection_items.sort_order')
                    ->orderBy('collection_items.added_at', 'desc');
    }

    public function followers(): HasMany
    {
        return $this->hasMany(CollectionFollower::class, 'collection_id');
    }

    public function followedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'collection_followers', 'collection_id', 'user_id')
                    ->withPivot('followed_at')
                    ->withTimestamps();
    }

    // Accessors
    public function getBusinessesCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getFollowersCountAttribute(): int
    {
        return $this->followers()->count();
    }

    // Helper methods
    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->where('user_id', $user->id)->exists();
    }

    public function containsBusiness(Business $business): bool
    {
        return $this->items()->where('business_id', $business->id)->exists();
    }

    public function addBusiness(Business $business, string $notes = null, int $sortOrder = 0): CollectionItem
    {
        return $this->items()->create([
            'business_id' => $business->id,
            'notes' => $notes,
            'sort_order' => $sortOrder,
            'added_at' => now()
        ]);
    }

    public function removeBusiness(Business $business): bool
    {
        return $this->items()->where('business_id', $business->id)->delete();
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount('followers')
                    ->orderBy('followers_count', 'desc')
                    ->limit($limit);
    }
}

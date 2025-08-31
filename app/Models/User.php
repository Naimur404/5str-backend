<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'profile_image',
        'current_latitude',
        'current_longitude',
        'city',
        'total_points',
        'total_reviews_written',
        'trust_level',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'total_points' => 'integer',
        'total_reviews_written' => 'integer',
        'trust_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function businesses()
    {
        return $this->hasMany(Business::class, 'owner_user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function points()
    {
        return $this->hasMany(UserPoint::class);
    }

    public function reviewHelpfulVotes()
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }

    public function offerUsages()
    {
        return $this->hasMany(UserOfferUsage::class);
    }

    public function searchLogs()
    {
        return $this->hasMany(SearchLog::class);
    }

    public function ownedBusinesses()
    {
        return $this->hasMany(Business::class, 'owner_user_id');
    }

    public function pushTokens()
    {
        return $this->hasMany(PushToken::class);
    }

    public function activePushTokens()
    {
        return $this->hasMany(PushToken::class)->where('is_active', true);
    }

    public function collections()
    {
        return $this->hasMany(UserCollection::class);
    }

    public function followedCollections()
    {
        return $this->belongsToMany(UserCollection::class, 'collection_followers', 'user_id', 'collection_id')
                    ->withPivot('followed_at')
                    ->withTimestamps();
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function interactions()
    {
        return $this->hasMany(UserInteraction::class);
    }

    /**
     * Check if user can access Filament admin panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin', 'moderator', 'business-owner']);
    }
}

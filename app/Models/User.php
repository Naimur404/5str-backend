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
        'google_id',
        'avatar',
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

    public function businessSubmissions()
    {
        return $this->hasMany(BusinessSubmission::class);
    }

    public function attractionSubmissions()
    {
        return $this->hasMany(AttractionSubmission::class);
    }

    public function offeringSubmissions()
    {
        return $this->hasMany(OfferingSubmission::class);
    }

    /**
     * Check if user can access Filament admin panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin', 'moderator', 'business-owner']);
    }

    /**
     * Check if user is a verified reviewer
     */
    public function isVerifiedReviewer(): bool
    {
        // Define criteria for a verified reviewer
        return $this->trust_level >= 3 && $this->total_reviews_written >= 5;
    }

    /**
     * Get user's achievements and badges
     */
    public function getAchievements(): array
    {
        $achievements = [];

        // Review milestones
        if ($this->total_reviews_written >= 100) {
            $achievements[] = ['name' => 'Master Reviewer', 'icon' => '🌟', 'description' => '100+ reviews written'];
        } elseif ($this->total_reviews_written >= 50) {
            $achievements[] = ['name' => 'Expert Reviewer', 'icon' => '⭐', 'description' => '50+ reviews written'];
        } elseif ($this->total_reviews_written >= 20) {
            $achievements[] = ['name' => 'Active Reviewer', 'icon' => '📝', 'description' => '20+ reviews written'];
        } elseif ($this->total_reviews_written >= 10) {
            $achievements[] = ['name' => 'Regular Contributor', 'icon' => '✍️', 'description' => '10+ reviews written'];
        }

        // Trust level badges
        switch ($this->trust_level) {
            case 5:
                $achievements[] = ['name' => 'Community Leader', 'icon' => '👑', 'description' => 'Maximum trust level'];
                break;
            case 4:
                $achievements[] = ['name' => 'Trusted Member', 'icon' => '🛡️', 'description' => 'High trust level'];
                break;
            case 3:
                $achievements[] = ['name' => 'Verified User', 'icon' => '✓', 'description' => 'Verified reviewer status'];
                break;
        }

        // Points milestones
        if ($this->total_points >= 10000) {
            $achievements[] = ['name' => 'Point Master', 'icon' => '💎', 'description' => '10,000+ points earned'];
        } elseif ($this->total_points >= 5000) {
            $achievements[] = ['name' => 'Point Collector', 'icon' => '💰', 'description' => '5,000+ points earned'];
        } elseif ($this->total_points >= 1000) {
            $achievements[] = ['name' => 'Point Earner', 'icon' => '🪙', 'description' => '1,000+ points earned'];
        }

        return $achievements;
    }

    /**
     * Get user's level based on points or activity
     */
    public function getUserLevel(): array
    {
        $points = $this->total_points ?? 0;
        $reviews = $this->total_reviews_written ?? 0;
        
        // Calculate level based on combined activity
        $activityScore = $points + ($reviews * 50); // Reviews worth 50 points each for level calculation
        
        if ($activityScore >= 20000) {
            return ['level' => 10, 'title' => 'Legend', 'icon' => '👑', 'color' => '#FFD700'];
        } elseif ($activityScore >= 15000) {
            return ['level' => 9, 'title' => 'Master', 'icon' => '🏆', 'color' => '#C0C0C0'];
        } elseif ($activityScore >= 10000) {
            return ['level' => 8, 'title' => 'Expert', 'icon' => '🥇', 'color' => '#CD7F32'];
        } elseif ($activityScore >= 7500) {
            return ['level' => 7, 'title' => 'Veteran', 'icon' => '🎖️', 'color' => '#9370DB'];
        } elseif ($activityScore >= 5000) {
            return ['level' => 6, 'title' => 'Advanced', 'icon' => '⭐', 'color' => '#FF6347'];
        } elseif ($activityScore >= 3000) {
            return ['level' => 5, 'title' => 'Experienced', 'icon' => '🌟', 'color' => '#32CD32'];
        } elseif ($activityScore >= 2000) {
            return ['level' => 4, 'title' => 'Regular', 'icon' => '📝', 'color' => '#4169E1'];
        } elseif ($activityScore >= 1000) {
            return ['level' => 3, 'title' => 'Active', 'icon' => '✍️', 'color' => '#FF8C00'];
        } elseif ($activityScore >= 500) {
            return ['level' => 2, 'title' => 'Contributor', 'icon' => '📋', 'color' => '#20B2AA'];
        } else {
            return ['level' => 1, 'title' => 'Newcomer', 'icon' => '🆕', 'color' => '#808080'];
        }
    }
}

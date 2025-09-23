<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmailVerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Generate a 6-digit verification code
     */
    public static function generateCode(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new verification code for an email
     */
    public static function createForEmail(string $email): self
    {
        // Delete any existing unverified codes for this email
        self::where('email', $email)
            ->where('is_verified', false)
            ->delete();

        return self::create([
            'email' => $email,
            'code' => self::generateCode(),
            'expires_at' => Carbon::now()->addMinutes(20), // 20 minutes expiry
        ]);
    }

    /**
     * Check if the code is expired
     */
    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Mark the code as verified
     */
    public function markAsVerified(): bool
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Find a valid verification code
     */
    public static function findValidCode(string $email, string $code): ?self
    {
        return self::where('email', $email)
            ->where('code', $code)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Check if email is verified (has a verified code)
     */
    public static function isEmailVerified(string $email): bool
    {
        return self::where('email', $email)
            ->where('is_verified', true)
            ->exists();
    }

    /**
     * Get the latest verification code for an email
     */
    public static function getLatestForEmail(string $email): ?self
    {
        return self::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Mail\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'city' => 'nullable|string',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if email already has a verified account
            if (EmailVerificationCode::isEmailVerified($request->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This email is already registered and verified. Please try logging in.'
                ], 409);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'city' => $request->city,
                'current_latitude' => $request->current_latitude,
                'current_longitude' => $request->current_longitude,
                'email_verified_at' => null, // Not verified yet
            ]);

            // Assign default user role
            try {
                $userRole = Role::where('name', 'user')->first();
                if ($userRole) {
                    $user->assignRole('user');
                    Log::info("User role assigned to user ID: {$user->id}");
                } else {
                    Log::warning("User role 'user' not found in database");
                }
            } catch (\Exception $e) {
                Log::error("Failed to assign user role: " . $e->getMessage());
                // Continue without role assignment - user can still function
            }

            // Generate verification code
            $verificationCode = EmailVerificationCode::createForEmail($request->email);

            // Send verification email
            Mail::to($request->email)->send(new EmailVerification($verificationCode, $request->name));

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email for verification code.',
                'data' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'verification_expires_at' => $verificationCode->expires_at,
                    'verification_required' => true
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated'
            ], 403);
        }

        // Check if email is verified
        if (!EmailVerificationCode::isEmailVerified($request->email)) {
            $latestCode = EmailVerificationCode::getLatestForEmail($request->email);
            
            if ($latestCode && $latestCode->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your verification code has expired. Please request a new one.',
                    'verification_required' => true,
                    'verification_expired' => true
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in. Check your inbox for the verification code.',
                'verification_required' => true,
                'verification_expires_at' => $latestCode ? $latestCode->expires_at : null
            ], 403);
        }

        // Update user location if provided
        if ($request->has('current_latitude') && $request->has('current_longitude')) {
            $user->update([
                'current_latitude' => $request->current_latitude,
                'current_longitude' => $request->current_longitude,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'city' => $user->city,
                    'is_active' => $user->is_active,
                    'role' => $user->roles->first()?->name ?? 'user'
                ]
            ]
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        // Count user's favorites and reviews
        $totalFavorites = $user->favorites()->count();
        $totalReviews = $user->reviews()->count();
        
        // Calculate user level based on points and activities
        $userLevel = $this->calculateUserLevel($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'city' => $user->city,
                    'profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                    'current_latitude' => $user->current_latitude,
                    'current_longitude' => $user->current_longitude,
                    'trust_level' => $user->trust_level,
                    'total_points' => $user->total_points,
                    'total_favorites' => $totalFavorites,
                    'total_reviews' => $totalReviews,
                    'total_submissions' => $this->getTotalSubmissionsCount($user),
                    'user_level' => $userLevel,
                    'is_active' => $user->is_active,
                    'role' => $user->roles->first()?->name ?? 'user',
                    'email_verified_at' => $user->email_verified_at
                ]
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
            'city' => 'sometimes|string',
            'current_latitude' => 'sometimes|numeric|between:-90,90',
            'current_longitude' => 'sometimes|numeric|between:-180,180',
            'profile_image' => 'sometimes|string', // Base64 string
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'name', 'phone', 'city', 'current_latitude', 'current_longitude'
            ]);

            // Handle base64 image upload
            if ($request->has('profile_image') && !empty($request->profile_image)) {
                $profileImagePath = $this->handleBase64ImageUpload($request->profile_image, $user->id);
                if ($profileImagePath) {
                    // Delete old profile image if exists
                    if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                        Storage::disk('public')->delete($user->profile_image);
                    }
                    $updateData['profile_image'] = $profileImagePath;
                }
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'city' => $user->city,
                        'profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                        'current_latitude' => $user->current_latitude,
                        'current_longitude' => $user->current_longitude,
                        'trust_level' => $user->trust_level,
                        'total_points' => $user->total_points,
                        'is_active' => $user->is_active,
                        'role' => $user->roles->first()?->name ?? 'user'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle base64 image upload
     */
    private function handleBase64ImageUpload(string $base64String, int $userId): ?string
    {
        try {
            // Check if the string contains data URL prefix
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
                $imageType = $matches[1]; // jpg, png, gif, etc.
                $base64String = substr($base64String, strpos($base64String, ',') + 1);
            } else {
                // Assume it's a plain base64 string, default to jpg
                $imageType = 'jpg';
            }

            // Validate image type
            if (!in_array($imageType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new \Exception('Invalid image type');
            }

            // Decode base64
            $imageData = base64_decode($base64String);
            
            if ($imageData === false) {
                throw new \Exception('Invalid base64 data');
            }

            // Validate image size (max 5MB)
            if (strlen($imageData) > 5 * 1024 * 1024) {
                throw new \Exception('Image size too large (max 5MB)');
            }

            // Generate unique filename
            $filename = 'profile_' . $userId . '_' . time() . '.' . $imageType;
            $filePath = 'profile-images/' . $filename;

            // Save to storage
            $saved = Storage::disk('public')->put($filePath, $imageData);
            
            if (!$saved) {
                throw new \Exception('Failed to save image');
            }

            return $filePath;

        } catch (\Exception $e) {
            // Log error and return null
            Log::error('Profile image upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate user level based on points, reviews, and activity
     */
    private function calculateUserLevel($user)
    {
        $points = $user->total_points ?? 0;
        $reviewsCount = $user->reviews()->count();
        $favoritesCount = $user->favorites()->count();
        $trustLevel = $user->trust_level ?? 1;
        
        // Calculate base score from points
        $pointsScore = min(100, ($points / 1000) * 100); // 1000 points = 100 score
        
        // Calculate activity score
        $activityScore = min(50, ($reviewsCount * 5) + ($favoritesCount * 2)); // Reviews worth more
        
        // Calculate trust score
        $trustScore = ($trustLevel / 5) * 30; // Trust level 1-5, max 30 points
        
        // Total score out of 180
        $totalScore = $pointsScore + $activityScore + $trustScore;
        
        // Determine level and level name
        if ($totalScore >= 150) {
            $level = 5;
            $levelName = 'Expert Explorer';
            $levelDescription = 'Master of discovery with exceptional contributions';
        } elseif ($totalScore >= 120) {
            $level = 4;
            $levelName = 'Seasoned Reviewer';
            $levelDescription = 'Experienced user with valuable insights';
        } elseif ($totalScore >= 80) {
            $level = 3;
            $levelName = 'Active Explorer';
            $levelDescription = 'Engaged user discovering local businesses';
        } elseif ($totalScore >= 40) {
            $level = 2;
            $levelName = 'Rising Contributor';
            $levelDescription = 'Growing presence in the community';
        } else {
            $level = 1;
            $levelName = 'New Explorer';
            $levelDescription = 'Just starting the discovery journey';
        }
        
        // Calculate progress to next level
        $nextLevelThresholds = [0, 40, 80, 120, 150, 180];
        $currentThreshold = $nextLevelThresholds[$level - 1] ?? 0;
        $nextThreshold = $nextLevelThresholds[$level] ?? 180;
        $progressToNext = $nextThreshold > $currentThreshold 
            ? (($totalScore - $currentThreshold) / ($nextThreshold - $currentThreshold)) * 100 
            : 100;
        
        return [
            'level' => $level,
            'level_name' => $levelName,
            'level_description' => $levelDescription,
            'total_score' => round($totalScore, 2),
            'progress_to_next_level' => round($progressToNext, 2),
            'points_contribution' => round($pointsScore, 2),
            'activity_contribution' => round($activityScore, 2),
            'trust_contribution' => round($trustScore, 2),
            'next_level_threshold' => $level < 5 ? $nextThreshold : null,
        ];
    }

    /**
     * Get total submissions count safely
     */
    private function getTotalSubmissionsCount($user)
    {
        try {
            $businessCount = method_exists($user, 'businessSubmissions') ? $user->businessSubmissions()->count() : 0;
            $attractionCount = method_exists($user, 'attractionSubmissions') ? $user->attractionSubmissions()->count() : 0;
            $offeringCount = method_exists($user, 'offeringSubmissions') ? $user->offeringSubmissions()->count() : 0;
            
            return $businessCount + $attractionCount + $offeringCount;
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::warning('Error calculating total submissions count: ' . $e->getMessage());
            return 0;
        }
    }
}

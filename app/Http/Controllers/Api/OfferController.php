<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Business;
use App\Models\UserOfferUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class OfferController extends Controller
{
    /**
     * Get offer details
     */
    public function show(Request $request, $offerId)
    {
        try {
            $offer = Offer::with(['business:id,business_name,slug', 'business.logoImage'])
                ->findOrFail($offerId);

            // Check if user has used this offer (if authenticated)
            $hasUsed = false;
            $usageCount = 0;
            $user = $this->getAuthenticatedUser($request);
            
            if ($user) {
                $userUsage = UserOfferUsage::where('user_id', $user->id)
                    ->where('offer_id', $offerId)
                    ->first();
                
                if ($userUsage) {
                    $hasUsed = true;
                    $usageCount = UserOfferUsage::where('user_id', $user->id)
                        ->where('offer_id', $offerId)
                        ->count();
                }
            }

            // Calculate remaining days
            $remainingDays = $offer->valid_to ? now()->diffInDays($offer->valid_to, false) : null;
            $isExpired = $remainingDays !== null && $remainingDays < 0;

            $data = [
                'id' => $offer->id,
                'title' => $offer->title,
                'description' => $offer->description,
                'offer_type' => $offer->offer_type,
                'discount_percentage' => $offer->discount_percentage,
                'discount_amount' => $offer->discount_amount,
                'minimum_spend' => $offer->minimum_spend,
                'offer_code' => $offer->offer_code,
                'usage_limit' => $offer->usage_limit,
                'current_usage' => $offer->current_usage,
                'valid_from' => $offer->valid_from?->format('Y-m-d'),
                'valid_to' => $offer->valid_to?->format('Y-m-d'),
                'applicable_days' => $offer->applicable_days,
                'banner_image' => $offer->banner_image,
                'is_featured' => $offer->is_featured,
                'is_active' => $offer->is_active,
                'is_expired' => $isExpired,
                'remaining_days' => max(0, $remainingDays ?? 0),
                'is_valid_today' => $offer->isValidToday(),
                'can_be_used' => $user ? $offer->canBeUsedBy($user->id) : $offer->isValidToday(),
                'user_has_used' => $hasUsed,
                'user_usage_count' => $usageCount,
                'business' => [
                    'id' => $offer->business->id,
                    'business_name' => $offer->business->business_name,
                    'slug' => $offer->business->slug,
                    'logo_image' => $offer->business->logoImage->image_url ?? null,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 404);
        }
    }

    /**
     * Get all active offers
     */
    public function index(Request $request)
    {
        try {
            $query = Offer::active()
                ->with(['business:id,business_name,slug', 'business.logoImage']);

            // Filter by business
            if ($request->has('business_id')) {
                $query->where('business_id', $request->business_id);
            }

            // Filter by offer type
            if ($request->has('offer_type')) {
                $query->where('offer_type', $request->offer_type);
            }

            // Filter featured offers
            if ($request->boolean('featured')) {
                $query->featured();
            }

            // Sort options
            $sortBy = $request->input('sort', 'created_at');
            switch ($sortBy) {
                case 'expiry':
                    $query->orderBy('valid_to', 'asc');
                    break;
                case 'discount':
                    $query->orderBy('discount_percentage', 'desc')
                          ->orderBy('discount_amount', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('current_usage', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            
            $offers = $query->paginate($limit, ['*'], 'page', $page);

            // Get authenticated user for usage info
            $user = $this->getAuthenticatedUser($request);

            $offersData = $offers->getCollection()->map(function($offer) use ($user) {
                $hasUsed = false;
                if ($user) {
                    $hasUsed = UserOfferUsage::where('user_id', $user->id)
                        ->where('offer_id', $offer->id)
                        ->exists();
                }

                $remainingDays = $offer->valid_to ? now()->diffInDays($offer->valid_to, false) : null;
                $isExpired = $remainingDays !== null && $remainingDays < 0;

                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'offer_type' => $offer->offer_type,
                    'discount_percentage' => $offer->discount_percentage,
                    'discount_amount' => $offer->discount_amount,
                    'minimum_spend' => $offer->minimum_spend,
                    'offer_code' => $offer->offer_code,
                    'valid_to' => $offer->valid_to?->format('Y-m-d'),
                    'banner_image' => $offer->banner_image,
                    'is_featured' => $offer->is_featured,
                    'is_expired' => $isExpired,
                    'remaining_days' => max(0, $remainingDays ?? 0),
                    'user_has_used' => $hasUsed,
                    'business' => [
                        'id' => $offer->business->id,
                        'business_name' => $offer->business->business_name,
                        'slug' => $offer->business->slug,
                        'logo_image' => $offer->business->logoImage->image_url ?? null,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'offers' => $offersData,
                    'pagination' => [
                        'current_page' => $offers->currentPage(),
                        'last_page' => $offers->lastPage(),
                        'per_page' => $offers->perPage(),
                        'total' => $offers->total(),
                        'has_more' => $offers->hasMorePages()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch offers',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Use an offer (track usage)
     */
    public function useOffer(Request $request, $offerId)
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to use offers'
                ], 401);
            }

            $offer = Offer::findOrFail($offerId);

            // Check if offer can be used
            if (!$offer->canBeUsedBy($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This offer cannot be used at this time'
                ], 400);
            }

            // Check if user has already used this offer (if single use)
            if ($offer->usage_limit === 1) {
                $existingUsage = UserOfferUsage::where('user_id', $user->id)
                    ->where('offer_id', $offerId)
                    ->exists();

                if ($existingUsage) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already used this offer'
                    ], 400);
                }
            }

            // Record the usage
            UserOfferUsage::create([
                'user_id' => $user->id,
                'offer_id' => $offerId,
                'used_at' => now(),
            ]);

            // Increment current usage count
            $offer->increment('current_usage');

            return response()->json([
                'success' => true,
                'message' => 'Offer used successfully',
                'data' => [
                    'offer_id' => $offerId,
                    'used_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to use offer',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get authenticated user from request (optional authentication)
     */
    private function getAuthenticatedUser(Request $request)
    {
        if ($request->hasHeader('Authorization')) {
            try {
                $authHeader = $request->header('Authorization');
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    
                    $accessToken = PersonalAccessToken::findToken($token);
                    if ($accessToken && $accessToken->tokenable) {
                        return $accessToken->tokenable;
                    }
                }
            } catch (\Exception $e) {
                // Token invalid or expired, continue as guest
            }
        }
        
        return null;
    }
}

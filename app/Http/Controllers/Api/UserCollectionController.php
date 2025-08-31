<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserCollection;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserCollectionController extends Controller
{
    /**
     * Display a listing of the user's collections.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $collections = UserCollection::byUser($user)
            ->with([
                'businesses:id,business_name,business_phone,full_address',
                'businesses.logoImage:id,business_id,image_url',
                'followers:id'
            ])
            ->withCount(['items', 'followers'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'collections' => $collections
            ]
        ]);
    }

    /**
     * Store a newly created collection.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048' // 2MB max
        ]);

        $user = Auth::user();
        
        // Handle cover image upload
        $coverImageUrl = null;
        if ($request->hasFile('cover_image')) {
            $image = $request->file('cover_image');
            $imageName = 'collection_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            
            // Store in public/storage/collections directory
            $imagePath = $image->storeAs('collections', $imageName, 'public');
            $coverImageUrl = asset('storage/' . $imagePath);
        }
        
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', false),
            'cover_image' => $coverImageUrl,
            'slug' => Str::slug($request->name . '-' . time())
        ]);

        $collection->load(['businesses:id,business_name,business_phone,full_address', 'businesses.logoImage:id,business_id,image_url']);

        return response()->json([
            'success' => true,
            'message' => 'Collection created successfully',
            'data' => [
                'collection' => $collection
            ]
        ], 201);
    }

    /**
     * Display the specified collection.
     */
    public function show($collectionId): JsonResponse
    {
        $user = Auth::user();
        
        // First try to find the collection owned by the user
        $collection = UserCollection::where('id', $collectionId)
                                   ->where('user_id', $user->id)
                                   ->first();
        
        // If not found, check if it's a public collection
        if (!$collection) {
            $collection = UserCollection::where('id', $collectionId)
                                       ->where('is_public', true)
                                       ->first();
        }
        
        if (!$collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found or access denied'
            ], 404);
        }

        $collection->load([
            'user:id,name',
            'businesses:id,business_name,business_phone,full_address,overall_rating',
            'businesses.logoImage:id,business_id,image_url',
            'items' => function($query) {
                $query->with([
                    'business:id,business_name,business_phone,full_address,overall_rating',
                    'business.logoImage:id,business_id,image_url'
                ])
                      ->orderBy('sort_order')
                      ->orderBy('added_at', 'desc');
            }
        ]);

        $collection->loadCount(['items', 'followers']);
        
        // Check if current user follows this collection
        $collection->is_followed_by_user = $collection->isFollowedBy($user);

        return response()->json([
            'success' => true,
            'data' => [
                'collection' => $collection
            ]
        ]);
    }

    /**
     * Update the specified collection.
     */
    public function update(Request $request, $collectionId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the collection that belongs to the authenticated user
        $collection = UserCollection::where('id', $collectionId)
                                   ->where('user_id', $user->id)
                                   ->first();
        
        if (!$collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found or you don\'t have permission to update it'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048' // 2MB max
        ]);

        // Handle cover image upload
        $coverImageUrl = $collection->cover_image; // Keep existing image by default
        if ($request->hasFile('cover_image')) {
            // Delete old image if it exists
            if ($collection->cover_image) {
                $oldImagePath = str_replace(url('storage/'), '', $collection->cover_image);
                if (Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }
            
            // Upload new image
            $image = $request->file('cover_image');
            $imageName = 'collection_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('collections', $imageName, 'public');
            $coverImageUrl = asset('storage/' . $imagePath);
        }

        $collection->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public'),
            'cover_image' => $coverImageUrl,
            'slug' => $request->name !== $collection->name 
                     ? Str::slug($request->name . '-' . time()) 
                     : $collection->slug
        ]);

        $collection->load(['businesses:id,business_name,business_phone,full_address', 'businesses.logoImage:id,business_id,image_url']);

        return response()->json([
            'success' => true,
            'message' => 'Collection updated successfully',
            'data' => [
                'collection' => $collection
            ]
        ]);
    }

    /**
     * Remove the specified collection.
     */
    public function destroy($collectionId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the collection that belongs to the authenticated user
        $collection = UserCollection::where('id', $collectionId)
                                   ->where('user_id', $user->id)
                                   ->first();
        
        if (!$collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found or you don\'t have permission to delete it'
            ], 404);
        }

        $collection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Collection deleted successfully'
        ]);
    }

    /**
     * Add a business to a collection.
     */
    public function addBusiness(Request $request, $collectionId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the collection that belongs to the authenticated user
        $collection = UserCollection::where('id', $collectionId)
                                   ->where('user_id', $user->id)
                                   ->first();
        
        if (!$collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found or you don\'t have permission to modify it'
            ], 404);
        }

        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'notes' => 'nullable|string|max:500',
            'sort_order' => 'integer|min:0'
        ]);

        $business = Business::findOrFail($request->business_id);

        // Check if business is already in collection
        if ($collection->containsBusiness($business)) {
            return response()->json([
                'success' => false,
                'message' => 'Business is already in this collection'
            ], 409);
        }

        $collectionItem = $collection->addBusiness(
            $business,
            $request->notes,
            $request->sort_order ?? 0
        );

        $collectionItem->load([
            'business:id,business_name,business_phone,full_address,overall_rating',
            'business.logoImage:id,business_id,image_url'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business added to collection successfully',
            'data' => [
                'collection_item' => $collectionItem
            ]
        ]);
    }

    /**
     * Remove a business from a collection.
     */
    public function removeBusiness($collectionId, $businessId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the collection that belongs to the authenticated user
        $collection = UserCollection::where('id', $collectionId)
                                   ->where('user_id', $user->id)
                                   ->first();
        
        if (!$collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found or you don\'t have permission to modify it'
            ], 404);
        }
        
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        if (!$collection->containsBusiness($business)) {
            return response()->json([
                'success' => false,
                'message' => 'Business is not in this collection'
            ], 404);
        }

        $collection->removeBusiness($business);

        return response()->json([
            'success' => true,
            'message' => 'Business removed from collection successfully'
        ]);
    }

    /**
     * Follow a public collection.
     */
    public function follow(UserCollection $collection): JsonResponse
    {
        $user = Auth::user();
        
        // Check if collection is public
        if (!$collection->is_public) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot follow private collections'
            ], 403);
        }

        // Check if user owns the collection
        if ($collection->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot follow your own collection'
            ], 409);
        }

        // Check if already following
        if ($collection->isFollowedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Already following this collection'
            ], 409);
        }

        $collection->followers()->create([
            'user_id' => $user->id,
            'followed_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Collection followed successfully'
        ]);
    }

    /**
     * Unfollow a collection.
     */
    public function unfollow(UserCollection $collection): JsonResponse
    {
        $user = Auth::user();
        
        if (!$collection->isFollowedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not following this collection'
            ], 404);
        }

        $collection->followers()->where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Collection unfollowed successfully'
        ]);
    }

    /**
     * Get popular public collections.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        
        $collections = UserCollection::public()
            ->with([
                'user:id,name', 
                'businesses:id,business_name',
                'businesses.logoImage:id,business_id,image_url'
            ])
            ->withCount(['items', 'followers'])
            ->popular($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'collections' => $collections
            ]
        ]);
    }

    /**
     * Search public collections.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'limit' => 'integer|min:1|max:50'
        ]);

        $query = $request->get('query');
        $limit = $request->get('limit', 10);

        $collections = UserCollection::public()
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->with([
                'user:id,name', 
                'businesses:id,business_name',
                'businesses.logoImage:id,business_id,image_url'
            ])
            ->withCount(['items', 'followers'])
            ->orderByDesc('followers_count')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'collections' => $collections,
                'query' => $query
            ]
        ]);
    }
}

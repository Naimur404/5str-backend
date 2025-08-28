<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserCollection;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
            ->with(['businesses:id,name,phone,address,image_url', 'followers:id'])
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
            'cover_image' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();
        
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', false),
            'cover_image' => $request->cover_image,
            'slug' => Str::slug($request->name . '-' . time())
        ]);

        $collection->load(['businesses:id,name,phone,address,image_url']);

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
    public function show(UserCollection $collection): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user can view this collection
        if (!$collection->is_public && $collection->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found or access denied'
            ], 404);
        }

        $collection->load([
            'user:id,name',
            'businesses:id,name,phone,address,image_url,rating',
            'items' => function($query) {
                $query->with('business:id,name,phone,address,image_url,rating')
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
    public function update(Request $request, UserCollection $collection): JsonResponse
    {
        $user = Auth::user();
        
        // Check ownership
        if ($collection->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this collection'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
            'cover_image' => 'nullable|string|max:500'
        ]);

        $collection->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public'),
            'cover_image' => $request->cover_image,
            'slug' => $request->name !== $collection->name 
                     ? Str::slug($request->name . '-' . time()) 
                     : $collection->slug
        ]);

        $collection->load(['businesses:id,name,phone,address,image_url']);

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
    public function destroy(UserCollection $collection): JsonResponse
    {
        $user = Auth::user();
        
        // Check ownership
        if ($collection->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this collection'
            ], 403);
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
    public function addBusiness(Request $request, UserCollection $collection): JsonResponse
    {
        $user = Auth::user();
        
        // Check ownership
        if ($collection->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this collection'
            ], 403);
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

        $collectionItem->load('business:id,name,phone,address,image_url,rating');

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
    public function removeBusiness(UserCollection $collection, Business $business): JsonResponse
    {
        $user = Auth::user();
        
        // Check ownership
        if ($collection->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this collection'
            ], 403);
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
            ->with(['user:id,name', 'businesses:id,name,image_url'])
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
            ->with(['user:id,name', 'businesses:id,name,image_url'])
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

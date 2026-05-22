<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\ReviewResource;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewAdminController extends AdminResourceController
{
    protected static string $model = Review::class;
    protected static string $resource = ReviewResource::class;
    protected static array $searchable = ['title'];
    protected static array $sortable = ['id', 'overall_rating', 'helpful_count', 'created_at', 'approved_at'];
    protected static array $filters = [
        'status' => 'status',
        'user_id' => 'user_id',
        'reviewable_id' => 'reviewable_id',
        'reviewable_type' => 'reviewable_type',
        'is_recommended' => 'is_recommended',
    ];
    protected static array $with = ['user:id,name,email', 'reviewable'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'user_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'reviewable_type' => [
                $isCreate ? 'required' : 'sometimes',
                Rule::in([
                    'App\\Models\\Business',
                    'App\\Models\\BusinessOffering',
                    'App\\Models\\Attraction',
                ]),
            ],
            'reviewable_id' => [$isCreate ? 'required' : 'sometimes', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'overall_rating' => [$isCreate ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'ratings' => ['nullable', 'array'],
            'is_recommended' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'flagged'])],
        ];
    }

    /**
     * GET /api/admin/reviews/rating-criteria
     * Returns the sub-rating criteria the given target expects, mirroring the
     * logic the public review flow uses (category rating_criteria for businesses,
     * type-specific defaults otherwise).
     */
    public function ratingCriteria(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reviewable_type' => ['required', 'string'],
            'reviewable_id' => ['nullable', 'integer'],
        ]);

        $criteria = Review::resolveCriteria($data['reviewable_type'], $data['reviewable_id'] ?? null);

        return response()->json([
            'success' => true,
            'data' => array_values($criteria),
        ]);
    }

    public function approve(Review $review): JsonResponse
    {
        $review->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review->fresh(static::$with)),
        ]);
    }

    public function reject(Review $review): JsonResponse
    {
        $review->forceFill([
            'status' => 'rejected',
            'approved_at' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review->fresh(static::$with)),
        ]);
    }
}

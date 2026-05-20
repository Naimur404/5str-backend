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
    protected static array $with = ['user:id,name,email'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'overall_rating' => ['nullable', 'integer', 'between:1,5'],
            'ratings' => ['nullable', 'array'],
            'is_recommended' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'flagged'])],
        ];
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

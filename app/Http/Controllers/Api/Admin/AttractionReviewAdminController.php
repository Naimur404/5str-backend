<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\AttractionReviewResource;
use App\Models\AttractionReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttractionReviewAdminController extends AdminResourceController
{
    protected static string $model = AttractionReview::class;
    protected static string $resource = AttractionReviewResource::class;
    protected static array $searchable = ['title', 'comment'];
    protected static array $sortable = ['id', 'rating', 'helpful_votes', 'created_at'];
    protected static array $filters = [
        'attraction_id' => 'attraction_id',
        'user_id' => 'user_id',
        'status' => 'status',
        'is_verified' => 'is_verified',
        'is_featured' => 'is_featured',
        'is_anonymous' => 'is_anonymous',
    ];
    protected static array $with = ['user:id,name'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric', 'between:1,5'],
            'is_verified' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_anonymous' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'flagged'])],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function approve(AttractionReview $review): JsonResponse
    {
        $review->forceFill(['status' => 'approved'])->save();

        return response()->json([
            'success' => true,
            'data' => new AttractionReviewResource($review->fresh(static::$with)),
        ]);
    }

    public function reject(Request $request, AttractionReview $review): JsonResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $review->forceFill([
            'status' => 'rejected',
            'admin_notes' => $data['admin_notes'],
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new AttractionReviewResource($review->fresh(static::$with)),
        ]);
    }
}

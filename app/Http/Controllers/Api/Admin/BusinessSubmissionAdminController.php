<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\BusinessResource as BusinessResourceTransformer;
use App\Http\Resources\Admin\BusinessSubmissionResource;
use App\Models\Business;
use App\Models\BusinessSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessSubmissionAdminController extends AdminResourceController
{
    protected static string $model = BusinessSubmission::class;
    protected static string $resource = BusinessSubmissionResource::class;
    protected static array $searchable = ['name', 'description', 'city', 'address', 'email', 'phone'];
    protected static array $sortable = ['id', 'name', 'status', 'created_at', 'reviewed_at'];
    protected static array $filters = [
        'status' => 'status',
        'user_id' => 'user_id',
        'city' => 'city',
    ];
    protected static array $with = [
        'user:id,name,email',
        'reviewer:id,name',
    ];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'opening_hours' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'additional_info' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }

    /**
     * POST /api/admin/submissions/businesses/{submission}/approve
     *
     * Creates a real Business record from the submission, links it back via
     * approved_business_id, and marks the submission approved. Accepts override
     * params (category_id, price_range, is_verified, etc.) since the submission
     * itself only carries a free-text category string.
     */
    public function approve(Request $request, BusinessSubmission $submission): JsonResponse
    {
        if ($submission->status === 'approved' && $submission->approved_business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Submission already approved.',
            ], 422);
        }

        $overrides = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price_range' => ['nullable', 'integer', 'between:1,4'],
            'is_verified' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'area' => ['nullable', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $business = DB::transaction(function () use ($submission, $overrides) {
            $business = Business::create([
                'business_name' => $submission->name,
                'slug' => $this->ensureUniqueBusinessSlug(Str::slug($submission->name)),
                'description' => $submission->description,
                'category_id' => $overrides['category_id'],
                'subcategory_id' => $overrides['subcategory_id'] ?? null,
                'owner_user_id' => $submission->user_id,
                'business_email' => $submission->email,
                'business_phone' => $submission->phone,
                'website_url' => $submission->website,
                'full_address' => $submission->address,
                'city' => $submission->city,
                'area' => $overrides['area'] ?? $submission->city,
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'opening_hours' => is_string($submission->opening_hours)
                    ? json_decode($submission->opening_hours, true)
                    : $submission->opening_hours,
                'price_range' => $overrides['price_range'] ?? 1,
                'is_verified' => $overrides['is_verified'] ?? true,
                'is_featured' => $overrides['is_featured'] ?? false,
                'is_active' => $overrides['is_active'] ?? true,
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $submission->forceFill([
                'status' => 'approved',
                'admin_notes' => $overrides['admin_notes'] ?? $submission->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'approved_business_id' => $business->id,
            ])->save();

            return $business;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => new BusinessSubmissionResource($submission->fresh(static::$with)),
                'business' => new BusinessResourceTransformer($business->load(['category:id,name'])),
            ],
        ]);
    }

    public function reject(Request $request, BusinessSubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $submission->forceFill([
            'status' => 'rejected',
            'admin_notes' => $data['admin_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new BusinessSubmissionResource($submission->fresh(static::$with)),
        ]);
    }

    private function ensureUniqueBusinessSlug(string $slug): string
    {
        $base = $slug;
        $i = 1;
        while (Business::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}

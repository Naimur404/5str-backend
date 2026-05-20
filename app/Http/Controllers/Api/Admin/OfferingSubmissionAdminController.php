<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\BusinessOfferingResource;
use App\Http\Resources\Admin\OfferingSubmissionResource;
use App\Models\BusinessOffering;
use App\Models\OfferingSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfferingSubmissionAdminController extends AdminResourceController
{
    protected static string $model = OfferingSubmission::class;
    protected static string $resource = OfferingSubmissionResource::class;
    protected static array $searchable = ['offering_name', 'offering_description', 'business_name'];
    protected static array $sortable = ['id', 'offering_name', 'status', 'created_at', 'reviewed_at'];
    protected static array $filters = [
        'status' => 'status',
        'user_id' => 'user_id',
        'business_id' => 'business_id',
        'offering_category' => 'offering_category',
    ];
    protected static array $with = [
        'user:id,name,email',
        'reviewer:id,name',
        'business:id,business_name',
    ];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }

    public function approve(Request $request, OfferingSubmission $submission): JsonResponse
    {
        if ($submission->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Submission already approved.',
            ], 422);
        }

        $overrides = $request->validate([
            'business_id' => [
                $submission->business_id ? 'nullable' : 'required',
                'integer', 'exists:businesses,id',
            ],
            'offering_type' => ['nullable', Rule::in(['product', 'service', 'menu'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'currency' => ['nullable', 'string', 'max:3'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $offering = DB::transaction(function () use ($submission, $overrides) {
            $offering = BusinessOffering::create([
                'business_id' => $overrides['business_id'] ?? $submission->business_id,
                'name' => $submission->offering_name,
                'description' => $submission->offering_description,
                'offering_type' => $overrides['offering_type'] ?? 'product',
                'category_id' => $overrides['category_id'] ?? null,
                'price' => $submission->price,
                'currency' => $overrides['currency'] ?? 'BDT',
                'is_available' => true,
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $submission->forceFill([
                'status' => 'approved',
                'admin_notes' => $overrides['admin_notes'] ?? $submission->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ])->save();

            return $offering;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => new OfferingSubmissionResource($submission->fresh(static::$with)),
                'offering' => new BusinessOfferingResource($offering->load(['business:id,business_name,parent_id'])),
            ],
        ]);
    }

    public function reject(Request $request, OfferingSubmission $submission): JsonResponse
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
            'data' => new OfferingSubmissionResource($submission->fresh(static::$with)),
        ]);
    }
}
